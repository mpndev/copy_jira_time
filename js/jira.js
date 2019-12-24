document.addEventListener("DOMContentLoaded", function(event) {
  new Vue({
    el: '#jira',
    data: {
      projects: [],
      host: drupalSettings.jira_transfer_logged_time.host,
      email: drupalSettings.jira_transfer_logged_time.jira_email,
      token: drupalSettings.jira_transfer_logged_time.jira_token,
      source_namespace: drupalSettings.jira_transfer_logged_time.source_jira_namespace,
      target_namespace: drupalSettings.jira_transfer_logged_time.target_jira_namespace,
      source_project: null,
      from: null,
      to: null,
      source_user: null,
      target_project: null,
      target_issue: null,
      target_user: null,
      target_email: '',
      target_token: '',
      low_opacity_for_not_duplicated: false,
      low_opacity_for_duplicated: false,
      table_headings: [[null, 'Worklog ID', 'Task ID', 'Task Description', 'Logged Time']],
      checked_all: false,
      checked_all_not_duplicated: true,
      any_checked: false,
      are_source_projects_loading: true,
      are_source_users_loading: true,
      are_source_issues_loading: true,
      are_source_worklogs_loading: true,
      are_target_projects_loading: true,
      are_target_users_loading: true,
      are_target_issues_loading: true,
      are_target_worklogs_loading: true
    },
    created() {
      this.resetData(null, null, true)
    },
    computed: {
      everything_is_loaded() {
        return (!this.are_source_projects_loading && !this.are_source_users_loading && !this.are_source_issues_loading && !this.are_source_worklogs_loading && !this.are_target_projects_loading && !this.are_target_users_loading && !this.are_target_issues_loading && !this.are_target_worklogs_loading)
      },
      select_options_for_source_projects() {
        const projects = [{
          key: null,
          name: `- Project from ${this.source_namespace}.atlassian.net -`
        }]
        this.projects.source.map(project => {
          projects.push(project)
        })
        return projects
      },
      select_options_for_source_users() {
        const users = [{
          accountId: null,
          displayName: `- User from ${this.source_namespace}.atlassian.net -`
        }]
        this.projects.source.map(project => {
          if (project.key === this.source_project) {
            project.users.map(user => {
              users.push(user)
            })
          }
        })
        return users
      },
      select_options_for_target_projects() {
        const projects = [{
          key: null,
          name: `- Projects from ${this.target_namespace}.atlassian.net -`
        }]
        this.projects.target.map(project => {
          projects.push(project)
        })
        return projects
      },
      select_options_for_target_users() {
        const users = [{
          accountId: null,
          displayName: `- User from ${this.target_namespace}.atlassian.net -`
        }]
        this.projects.target.map(project => {
          if (project.key === this.target_project) {
            project.users.map(user => {
              users.push(user)
            })
          }
        })
        return users
      },
      select_options_for_target_issues() {
        const issues = [{
          id: null,
          key: `- Task from ${this.target_namespace}.atlassian.net -`
        }]
        this.projects.target.map(project => {
          if (project.key === this.target_project) {
            project.users.map(user => {
              if (user.accountId === this.target_user) {
                user.issues.map(issue => {
                  issues.push(issue)
                })
              }
            })
          }
        })
        return issues
      },
      table_rows() {
        const rows = []
        this.projects.source.map(project => {
          if (project.key === this.source_project) {
            project.users.map(user => {
              if (user.accountId === this.source_user) {
                user.issues.map(issue => {
                  issue.worklogs.map(worklog => {
                    if (worklog.accountId === user.accountId) {
                      let text
                      try {
                        text = worklog.comment.content[0].content[0].text
                      } catch (e) {
                        text = ''
                      }
                      rows.push({
                        worklogId: worklog.id,
                        taskId: issue.id,
                        taskDescription: text,
                        loggedTime: worklog.timeSpent,
                        checked: (this.isDuplicate(worklog) ? false : true),
                        is_duplicate: this.isDuplicate(worklog)
                      })
                      if (!this.isDuplicate(worklog)) {
                        this.any_checked = true
                      }
                    }
                  })
                })
              }
            })
          }
        })
        return rows
      },
      preview() {
        let task
        let username
        let preview = []
        this.projects.target.map(project => project.users.map(user => {
          if (user.accountId === this.target_user) {
            username = user.displayName
          }
          user.issues.map(issue => {
            if (issue.id === this.target_issue) {
              task = issue.key
            }
          })
        }))
        this.projects.target.map(project => {
          if (project.key === this.target_project) {
            project.users.map(user => {
              if (user.accountId === this.target_user) {
                user.issues.map(issue => {
                  if (issue.id === this.target_issue) {
                    issue.worklogs.map(worklog => {
                      this.table_rows.map(computed_worklog => {
                        if (computed_worklog.worklogId === worklog.id && computed_worklog.checked === true) {
                          preview.push(`log "${worklog.timeSpent}" with description "${computed_worklog.taskDescription}" in JIRA with namespace "${this.target_namespace}" in task "${task}" for user "${username}" with email "${this.target_email}" and token "${this.target_token}".\n\n`)
                        }
                      })
                    })
                  }
                })
              }
            })
          }
        })
        return preview.join(' ')
      },
      worklogs_to_submit() {
        let worklogs = []
        this.projects.target.map(project => {
          if (project.key === this.target_project) {
            project.users.map(user => {
              if (user.accountId === this.target_user) {
                user.issues.map(issue => {
                  if (issue.id === this.target_issue) {
                    issue.worklogs.map(worklog => {
                      this.table_rows.map(computed_worklog => {
                        if (computed_worklog.worklogId === worklog.id && computed_worklog.checked === true) {
                          worklog.taskDescription = computed_worklog.taskDescription
                          worklogs.push(worklog)
                        }
                      })
                    })
                  }
                })
              }
            })
          }
        })
        return worklogs
      }
    },
    methods: {
      isDuplicate(source_worklog) {
        return this.projects.target.some(project => {
          return project.users.some(user => {
            return user.issues.some(issue => {
              return issue.worklogs.some(worklog => {
                return worklog.started === source_worklog.created
              })
            })
          })
        })
      },
      handleCheckAllCheckbox() {
        this.checked_all_not_duplicated = false
        this.checked_all = !this.checked_all
        if (this.checked_all) {
          this.any_checked_not_duplicated = false
          this.table_rows.map(row => row.checked = true)
          this.any_checked = true
        } else {
          this.table_rows.map(row => row.checked = false)
          this.any_checked = false
        }
      },
      handleCheckAllNotDuplicatedCheckbox() {
        this.checked_all = false
        this.checked_all_not_duplicated = !this.checked_all_not_duplicated
        if (this.checked_all_not_duplicated) {
          this.table_rows.map(row => {
            if (row.is_duplicate) {
              row.checked = false
            } else {
              row.checked = true
              this.any_checked = true
            }
          })
        } else {
          this.table_rows.map(row => row.checked = false)
          this.any_checked = false
        }
      },
      rowWasClicked(row_index) {
        this.checked_all = !this.checked_all
        this.checked_all = !this.checked_all
        this.table_rows.map((row, index) =>{
          if (index === row_index) {
            row.checked = !row.checked
          }
        })
        const turn_off_checked_all = this.table_rows.some(row => row.checked === false)
        if (turn_off_checked_all) {
          this.checked_all = false
        }
        const turn_on_checked_all = this.table_rows.every(row => row.checked === true)
        if (turn_on_checked_all) {
          this.checked_all = true
        }
        this.any_checked = this.table_rows.some(row => row.checked === true)
        const turn_on_checked_all_not_duplicated = this.table_rows.filter(row => row.is_duplicate === false).every(duplicated_row => duplicated_row.checked === true)
        const have_at_least_one_checked_duplicate = this.table_rows.filter(row => row.is_duplicate === true).some(not_duplicated_row => not_duplicated_row.checked === true)
        this.checked_all_not_duplicated = turn_on_checked_all_not_duplicated && !have_at_least_one_checked_duplicate;
      },
      submit() {
        if (this.target_namespace, this.target_project, this.target_issue, this.target_user, this.target_email, this.target_token, this.any_checked) {
          const agree = confirm(this.preview)
          if (agree) {
            const data = {
              namespace: this.target_namespace,
              project: this.target_project,
              issue: this.target_issue,
              user: this.target_user,
              email: this.target_email,
              token: this.target_token,
              worklogs: this.worklogs_to_submit
            }
            axios
              .post(`./jira/submit`, data)
              .then(response => {
                this.resetData()
                alert(response.data)
              })
          }
        } else {
          alert('Incorrect or incomplete data!')
        }
      },
      resetData(new_from = null, new_to = null, not_only_source = false) {
        if (new_to) {
          this.to = new_to
        } else {
          let time = new Date()
          this.to = time.toISOString().slice(0, 10)
        }
        if (new_from) {
          this.from = new_from
        } else {
          let time = new Date()
          time.setMonth(time.getMonth() - 1);
          this.from = time.toISOString().slice(0, 10)
        }
        this.source_project = null
        this.source_user = null
        this.low_opacity_for_not_duplicated = false
        this.low_opacity_for_duplicated = false
        this.checked_all = false
        this.checked_all_not_duplicated = true
        this.any_checked = false
        this.are_source_projects_loading = true
        this.are_source_users_loading = true
        this.are_source_issues_loading = true
        this.are_source_worklogs_loading = true
        if (not_only_source) {
          this.target_project = null
          this.target_issue = null
          this.target_user = null
          this.target_email = ''
          this.target_token = ''
          this.are_target_projects_loading = true
          this.are_target_users_loading = true
          this.are_target_issues_loading = true
          this.are_target_worklogs_loading = true
        }
        this.fillData(not_only_source)
      },
      fillData(not_only_source = false) {
        axios.get(`./jira/projects?prefix=source`)
        .then(response => {
          const projects_quantity = response.data.length
          this.projects.source = response.data
          this.projects.source.map((project, project_index) => {
            if (project_index + 1 === projects_quantity) {
              this.are_source_projects_loading = false
            }
            axios.get(`./jira/users?prefix=${this.source_namespace}`)
            .then(response => {
              const users_quantity = response.data.length
              project.users = response.data
              project.users.map((user, user_index) => {
                if (project_index + 1 === projects_quantity && user_index+1 === users_quantity) {
                  this.are_source_users_loading = false
                }
                axios.get(`./jira/issues?prefix=${this.source_namespace}&project_key=${project.key}`)
                .then(response => {
                  const issues_quantity = response.data.length
                  user.issues = response.data
                  user.issues.map((issue, issue_index) => {
                    if (project_index + 1 === projects_quantity && user_index+1 === users_quantity && issue_index+1 === issues_quantity) {
                      this.are_source_issues_loading = false
                    }
                    const users_accounts_ids = project.users.map(user => user = user.accountId)
                    axios.get(`./jira/issues-worklogs?prefix=${this.source_namespace}&issue_key=${issue.key}&users_accounts_ids=${users_accounts_ids}&start_date=${this.from}&end_date=${this.to}`)
                    .then(response => {
                      issue.worklogs = response.data
                      if (project_index + 1 === projects_quantity && user_index+1 === users_quantity && issue_index+1 === issues_quantity) {
                        this.are_source_worklogs_loading = false
                      }
                    })
                  })
                })
              })
            })
          })
        })
        if (not_only_source) {
          axios.get(`./jira/projects?prefix=target`)
          .then(response => {
            const projects_quantity = response.data.length
            this.projects.target = response.data
            this.projects.target.map((project, project_index) => {
              if (project_index+1 === projects_quantity) {
                this.are_target_projects_loading = false
              }
              axios.get(`./jira/users?prefix=${this.target_namespace}`)
              .then(response => {
                const users_quantity = response.data.length
                project.users = response.data
                project.users.map((user, user_index) => {
                  if (project_index+1 === projects_quantity && user_index+1 === users_quantity) {
                    this.are_target_users_loading = false
                  }
                  axios.get(`./jira/issues?prefix=${this.target_namespace}&project_key=${project.key}`)
                  .then(response => {
                    const issues_quantity = response.data.length
                    user.issues = response.data
                    user.issues.map((issue, issue_index) => {
                      if (project_index+1 === projects_quantity && user_index+1 === users_quantity && issue_index+1 === issues_quantity) {
                        this.are_target_issues_loading = false
                      }
                      const users_accounts_ids = project.users.map(user => user = user.accountId)
                      axios.get(`./jira/issues-worklogs?prefix=${this.target_namespace}&issue_key=${issue.key}&users_accounts_ids=${users_accounts_ids}&start_date=${this.from}&end_date=${this.to}`)
                      .then(response => {
                        issue.worklogs = response.data
                        if (project_index+1 === projects_quantity && user_index+1 === users_quantity && issue_index+1 === issues_quantity) {
                          this.are_target_worklogs_loading = false
                        }
                      })
                    })
                  })
                })
              })
            })
          })
        }
      },
      dateFromWasChanged(value) {
        this.from = value
        this.resetData(this.from, this.to)
      },
      dateToWasChanged(value) {
        this.to = value
        this.resetData(this.from, this.to)
      }
    }
  })
})