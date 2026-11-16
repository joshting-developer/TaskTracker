// Generated from backend/resources/openapi.json; checked by the contract test.
export const TOOLS = {
  "who_am_i": {
    "operationId": "getTokenIdentity",
    "path": "/api/public/v1/me",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_projects": {
    "operationId": "listPublicProjects",
    "path": "/api/public/v1/projects",
    "inputSchema": {
      "type": "object",
      "properties": {
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        },
        "search": {
          "type": "string",
          "maxLength": 200
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "search_issues": {
    "operationId": "listPublicIssues",
    "path": "/api/public/v1/projects/{project}/issues",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        },
        "search": {
          "type": "string",
          "maxLength": 200
        },
        "identifier": {
          "type": "string",
          "maxLength": 100
        },
        "state[]": {
          "type": "array",
          "maxItems": 30,
          "items": {
            "type": "string",
            "maxLength": 40
          }
        },
        "priority": {
          "enum": [
            "urgent",
            "high",
            "normal",
            "low"
          ]
        },
        "type": {
          "enum": [
            "epic",
            "task",
            "bug"
          ]
        },
        "assignee": {
          "type": "string",
          "maxLength": 100
        },
        "epicId": {
          "type": "string",
          "maxLength": 100
        },
        "condition": {
          "enum": [
            "overdue",
            "unassigned"
          ]
        },
        "sort": {
          "enum": [
            "updated",
            "created",
            "priority",
            "due"
          ],
          "default": "updated"
        },
        "direction": {
          "enum": [
            "asc",
            "desc"
          ]
        },
        "scope": {
          "enum": [
            "all",
            "mine"
          ],
          "default": "all"
        },
        "tagIds": {
          "type": "array",
          "maxItems": 20,
          "uniqueItems": true,
          "items": {
            "type": "string",
            "format": "uuid"
          }
        }
      },
      "required": [
        "project"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "get_issue": {
    "operationId": "getPublicIssue",
    "path": "/api/public/v1/projects/{project}/issues/{issue}",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "issue": {
          "type": "string",
          "maxLength": 100,
          "description": "Public UUID or exact identifier such as TT-341. Never an internal numeric ID."
        }
      },
      "required": [
        "project",
        "issue"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_issue_comments": {
    "operationId": "listPublicIssueComments",
    "path": "/api/public/v1/projects/{project}/issues/{issue}/comments",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "issue": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        }
      },
      "required": [
        "project",
        "issue"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_boards": {
    "operationId": "listPublicBoards",
    "path": "/api/public/v1/projects/{project}/boards",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        },
        "search": {
          "type": "string",
          "maxLength": 200
        },
        "archived": {
          "type": "integer",
          "enum": [
            0,
            1
          ],
          "default": 0
        }
      },
      "required": [
        "project"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "get_board": {
    "operationId": "getPublicBoard",
    "path": "/api/public/v1/projects/{project}/boards/{board}",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "board": {
          "type": "string",
          "pattern": "^(default|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$"
        }
      },
      "required": [
        "project",
        "board"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_board_issues": {
    "operationId": "listPublicBoardIssues",
    "path": "/api/public/v1/projects/{project}/boards/{board}/issues",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "board": {
          "type": "string",
          "pattern": "^(default|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$"
        },
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        },
        "search": {
          "type": "string",
          "maxLength": 200
        },
        "identifier": {
          "type": "string",
          "maxLength": 100
        },
        "state[]": {
          "type": "array",
          "maxItems": 30,
          "items": {
            "type": "string",
            "maxLength": 40
          }
        },
        "priority": {
          "enum": [
            "urgent",
            "high",
            "normal",
            "low"
          ]
        },
        "type": {
          "enum": [
            "epic",
            "task",
            "bug"
          ]
        },
        "assignee": {
          "type": "string",
          "maxLength": 100
        },
        "epicId": {
          "type": "string",
          "maxLength": 100
        },
        "condition": {
          "enum": [
            "overdue",
            "unassigned"
          ]
        },
        "sort": {
          "enum": [
            "updated",
            "created",
            "priority",
            "due"
          ],
          "default": "updated"
        },
        "direction": {
          "enum": [
            "asc",
            "desc"
          ]
        },
        "scope": {
          "enum": [
            "all",
            "mine"
          ],
          "default": "all"
        },
        "tagIds": {
          "type": "array",
          "maxItems": 20,
          "uniqueItems": true,
          "items": {
            "type": "string",
            "format": "uuid"
          }
        }
      },
      "required": [
        "project",
        "board"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "get_project": {
    "operationId": "getPublicProject",
    "path": "/api/public/v1/projects/{project}",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        }
      },
      "required": [
        "project"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_project_members": {
    "operationId": "listPublicProjectMembers",
    "path": "/api/public/v1/projects/{project}/members",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        },
        "search": {
          "type": "string",
          "maxLength": 200
        }
      },
      "required": [
        "project"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_project_statuses": {
    "operationId": "listPublicProjectStatuses",
    "path": "/api/public/v1/projects/{project}/statuses",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        }
      },
      "required": [
        "project"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "create_issue": {
    "operationId": "createPublicIssue",
    "method": "POST",
    "path": "/api/public/v1/projects/{project}/issues",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "body": {
          "type": "object",
          "properties": {
            "title": {
              "type": "string",
              "minLength": 1,
              "maxLength": 180,
              "pattern": "\\S"
            },
            "description": {
              "type": "string",
              "maxLength": 6000
            },
            "state": {
              "type": "string",
              "maxLength": 100
            },
            "priority": {
              "enum": [
                "urgent",
                "high",
                "normal",
                "low"
              ]
            },
            "type": {
              "enum": [
                "epic",
                "task",
                "bug"
              ]
            },
            "assignee": {
              "type": "string",
              "maxLength": 60
            },
            "dueDate": {
              "type": "string",
              "description": "YYYY-MM-DD\u6216\u7a7a\u5b57\u4e32\u6e05\u9664"
            },
            "epicId": {
              "type": "string",
              "maxLength": 100
            },
            "parentIssueId": {
              "type": "string",
              "maxLength": 100
            },
            "relatedIssueIds": {
              "type": "array",
              "items": {
                "type": "string",
                "maxLength": 100
              },
              "maxItems": 200,
              "uniqueItems": true
            },
            "estimateMinutes": {
              "type": "integer",
              "minimum": 0,
              "maximum": 525600
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            },
            "boardIds": {
              "type": "array",
              "maxItems": 20,
              "uniqueItems": true,
              "items": {
                "type": "string",
                "minLength": 1,
                "maxLength": 100
              },
              "description": "Optional active custom boards in this Project; requires board.manage. Default is implicit and must not be supplied. Any invalid board rolls back the entire issue creation."
            },
            "startDate": {
              "anyOf": [
                {
                  "type": "string",
                  "format": "date",
                  "pattern": "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$"
                },
                {
                  "const": ""
                },
                {
                  "type": "null"
                }
              ],
              "description": "Optional planned calendar date (1000-01-01 through 9999-12-31), independent of dueDate. Empty string or null clears input; omission on PATCH preserves it. When both are set, endDate must not precede startDate. Responses use an empty string for unset dates."
            },
            "endDate": {
              "anyOf": [
                {
                  "type": "string",
                  "format": "date",
                  "pattern": "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$"
                },
                {
                  "const": ""
                },
                {
                  "type": "null"
                }
              ],
              "description": "Optional planned calendar date (1000-01-01 through 9999-12-31), independent of dueDate. Empty string or null clears input; omission on PATCH preserves it. When both are set, endDate must not precede startDate. Responses use an empty string for unset dates."
            }
          },
          "required": [
            "title"
          ],
          "description": "JSON\u7269\u4ef6\u6700\u591a50000 bytes\uff1b\u672a\u77e5\u6b04\u4f4d\u5ffd\u7565\uff0c\u8def\u5f91\u63a7\u5236\u8cc7\u6e90\uff0cactor\uff0fWorkspace\u4e0d\u53ef\u7531payload\u8b8a\u66f4\u3002",
          "additionalProperties": false
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "project",
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "update_issue": {
    "operationId": "updatePublicIssue",
    "method": "PATCH",
    "path": "/api/public/v1/projects/{project}/issues/{issue}",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "issue": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "body": {
          "type": "object",
          "properties": {
            "title": {
              "type": "string",
              "minLength": 1,
              "maxLength": 180,
              "pattern": "\\S"
            },
            "description": {
              "type": "string",
              "maxLength": 6000
            },
            "state": {
              "type": "string",
              "maxLength": 100
            },
            "priority": {
              "enum": [
                "urgent",
                "high",
                "normal",
                "low"
              ]
            },
            "type": {
              "enum": [
                "epic",
                "task",
                "bug"
              ]
            },
            "assignee": {
              "type": "string",
              "maxLength": 60
            },
            "dueDate": {
              "type": "string",
              "description": "YYYY-MM-DD\u6216\u7a7a\u5b57\u4e32\u6e05\u9664"
            },
            "epicId": {
              "type": "string",
              "maxLength": 100
            },
            "parentIssueId": {
              "type": "string",
              "maxLength": 100
            },
            "relatedIssueIds": {
              "type": "array",
              "items": {
                "type": "string",
                "maxLength": 100
              },
              "maxItems": 200,
              "uniqueItems": true
            },
            "estimateMinutes": {
              "type": "integer",
              "minimum": 0,
              "maximum": 525600
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            },
            "expectedRevision": {
              "type": "integer",
              "minimum": 1,
              "description": "\u5f9e\u55ae\u5361GET\u53d6\u5f97\u7684\u6700\u65b0revision\uff0c\u56b4\u683cJSON\u6574\u6578\uff0c\u4e0d\u63a5\u53d7\u5b57\u4e32"
            },
            "startDate": {
              "anyOf": [
                {
                  "type": "string",
                  "format": "date",
                  "pattern": "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$"
                },
                {
                  "const": ""
                },
                {
                  "type": "null"
                }
              ],
              "description": "Optional planned calendar date (1000-01-01 through 9999-12-31), independent of dueDate. Empty string or null clears input; omission on PATCH preserves it. When both are set, endDate must not precede startDate. Responses use an empty string for unset dates."
            },
            "endDate": {
              "anyOf": [
                {
                  "type": "string",
                  "format": "date",
                  "pattern": "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$"
                },
                {
                  "const": ""
                },
                {
                  "type": "null"
                }
              ],
              "description": "Optional planned calendar date (1000-01-01 through 9999-12-31), independent of dueDate. Empty string or null clears input; omission on PATCH preserves it. When both are set, endDate must not precede startDate. Responses use an empty string for unset dates."
            }
          },
          "required": [
            "expectedRevision"
          ],
          "description": "JSON\u7269\u4ef6\u6700\u591a50000 bytes\uff1b\u672a\u77e5\u6b04\u4f4d\u5ffd\u7565\uff0c\u8def\u5f91\u63a7\u5236\u8cc7\u6e90\uff0cactor\uff0fWorkspace\u4e0d\u53ef\u7531payload\u8b8a\u66f4\u3002",
          "additionalProperties": false
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "project",
        "issue",
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "comment_issue": {
    "operationId": "createPublicIssueComment",
    "method": "POST",
    "path": "/api/public/v1/projects/{project}/issues/{issue}/comments",
    "inputSchema": {
      "type": "object",
      "properties": {
        "project": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "issue": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "body": {
          "type": "object",
          "properties": {
            "content": {
              "type": "string",
              "minLength": 1,
              "maxLength": 2000,
              "description": "Markdown\u539f\u7a3f\u4e0a\u96502000\u5b57, \u5716\u7247\u4f7f\u7528![\u540d\u7a31](attachment:UUID)\u3002\u5148\u4e0a\u50b3\u540c\u5c08\u6848\u5716\u7247, \u7559\u8a00\u6210\u529f\u6642\u540c\u4ea4\u6613\u7d81\u5b9a\u672c\u4eba\u672a\u904e\u671f\u7684pending\u9644\u4ef6\uff1b\u53ef\u91cd\u7528\u9019\u5f35\u5361\u5df2\u7d81\u5b9a\u7684\u5716\u7247, \u4e0d\u53ef\u5f15\u7528\u5176\u4ed6\u5361\u7247\u6216\u5176\u4ed6\u4eba\u7684pending\u9644\u4ef6"
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            },
            "expectedRevision": {
              "type": "integer",
              "minimum": 1,
              "description": "\u5f9e\u55ae\u5361GET\u53d6\u5f97\u7684\u6700\u65b0revision\uff0c\u56b4\u683cJSON\u6574\u6578\uff0c\u4e0d\u63a5\u53d7\u5b57\u4e32"
            }
          },
          "required": [
            "content",
            "expectedRevision"
          ],
          "description": "JSON\u7269\u4ef6\u6700\u591a50000 bytes\uff1b\u672a\u77e5\u6b04\u4f4d\u5ffd\u7565\uff0c\u8def\u5f91\u63a7\u5236\u8cc7\u6e90\uff0cactor\uff0fWorkspace\u4e0d\u53ef\u7531payload\u8b8a\u66f4\u3002",
          "additionalProperties": false
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "project",
        "issue",
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_todos": {
    "operationId": "listPublicTodos",
    "path": "/api/public/v1/todos",
    "inputSchema": {
      "type": "object",
      "properties": {
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        },
        "scope": {
          "enum": [
            "mine",
            "sent",
            "involved"
          ],
          "default": "mine"
        },
        "state": {
          "enum": [
            "open",
            "closed",
            "all"
          ]
        },
        "search": {
          "type": "string",
          "maxLength": 200
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "get_todo": {
    "operationId": "getPublicTodo",
    "path": "/api/public/v1/todos/{todo}",
    "inputSchema": {
      "type": "object",
      "properties": {
        "todo": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        }
      },
      "required": [
        "todo"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_todo_assignees": {
    "operationId": "listPublicTodoAssignees",
    "path": "/api/public/v1/todo-assignees",
    "inputSchema": {
      "type": "object",
      "properties": {
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        },
        "search": {
          "type": "string",
          "maxLength": 200
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_todo_comments": {
    "operationId": "getPublicTodoComments",
    "path": "/api/public/v1/todos/{todo}/comments",
    "inputSchema": {
      "type": "object",
      "properties": {
        "todo": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        }
      },
      "required": [
        "todo"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "list_todo_handoffs": {
    "operationId": "getPublicTodoHandoffs",
    "path": "/api/public/v1/todos/{todo}/handoffs",
    "inputSchema": {
      "type": "object",
      "properties": {
        "todo": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "page": {
          "type": "integer",
          "minimum": 1,
          "maximum": 1000000,
          "default": 1
        },
        "perPage": {
          "type": "integer",
          "minimum": 1,
          "maximum": 20,
          "default": 20
        }
      },
      "required": [
        "todo"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "create_todo": {
    "operationId": "createPublicTodo",
    "method": "POST",
    "path": "/api/public/v1/todos",
    "inputSchema": {
      "type": "object",
      "properties": {
        "body": {
          "type": "object",
          "additionalProperties": false,
          "required": [],
          "properties": {
            "title": {
              "type": "string",
              "minLength": 1,
              "maxLength": 180,
              "description": "\u6458\u8981\uff1b\u8207content\u4e8c\u9078\u4e00"
            },
            "note": {
              "type": "string",
              "maxLength": 2000
            },
            "content": {
              "type": "string",
              "maxLength": 6000,
              "description": "Markdown\u5168\u6587\uff1b\u7b2c\u4e00\u884c\u81ea\u52d5\u6210\u70ba\u6458\u8981"
            },
            "assigneeId": {
              "type": "string",
              "maxLength": 100,
              "description": "\u4ea4\u8fa6\u5c0d\u8c61User ID\uff08\u5f9e/todo-assignees\u53d6\u5f97\uff09\uff1b\u7701\u7565\u6642\u662f\u81ea\u5df1\u7684\u7d19\u689d"
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            }
          }
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "update_todo": {
    "operationId": "patchPublicTodo",
    "method": "PATCH",
    "path": "/api/public/v1/todos/{todo}",
    "inputSchema": {
      "type": "object",
      "properties": {
        "todo": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "body": {
          "type": "object",
          "additionalProperties": false,
          "required": [
            "expectedRevision"
          ],
          "properties": {
            "title": {
              "type": "string",
              "minLength": 1,
              "maxLength": 180,
              "description": "\u6458\u8981\uff1b\u8207content\u4e8c\u9078\u4e00"
            },
            "note": {
              "type": "string",
              "maxLength": 2000
            },
            "content": {
              "type": "string",
              "maxLength": 6000,
              "description": "Markdown\u5168\u6587\uff1b\u7b2c\u4e00\u884c\u81ea\u52d5\u6210\u70ba\u6458\u8981"
            },
            "expectedRevision": {
              "type": "integer",
              "minimum": 1
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            }
          }
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "todo",
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "complete_todo": {
    "operationId": "completePublicTodo",
    "method": "POST",
    "path": "/api/public/v1/todos/{todo}/complete",
    "inputSchema": {
      "type": "object",
      "properties": {
        "todo": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "body": {
          "type": "object",
          "additionalProperties": false,
          "required": [
            "expectedRevision"
          ],
          "properties": {
            "expectedRevision": {
              "type": "integer",
              "minimum": 1
            },
            "note": {
              "type": "string",
              "maxLength": 2000,
              "description": "\u9000\u56de\u6642\u53ef\u9644\u539f\u56e0"
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            },
            "reply": {
              "type": "string",
              "maxLength": 2000,
              "description": "\u5b8c\u6210\u6216\u9000\u56de\u6642\u9078\u586b\u7684\u7559\u8a00, \u8207\u72c0\u614b\u540c\u4ea4\u6613\u4fdd\u5b58"
            }
          }
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "todo",
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "reopen_todo": {
    "operationId": "reopenPublicTodo",
    "method": "POST",
    "path": "/api/public/v1/todos/{todo}/reopen",
    "inputSchema": {
      "type": "object",
      "properties": {
        "todo": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "body": {
          "type": "object",
          "additionalProperties": false,
          "required": [
            "expectedRevision"
          ],
          "properties": {
            "expectedRevision": {
              "type": "integer",
              "minimum": 1
            },
            "note": {
              "type": "string",
              "maxLength": 2000,
              "description": "\u9000\u56de\u6642\u53ef\u9644\u539f\u56e0"
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            },
            "reply": {
              "type": "string",
              "maxLength": 2000,
              "description": "\u5b8c\u6210\u6216\u9000\u56de\u6642\u9078\u586b\u7684\u7559\u8a00, \u8207\u72c0\u614b\u540c\u4ea4\u6613\u4fdd\u5b58"
            }
          }
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "todo",
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "return_todo": {
    "operationId": "returnPublicTodo",
    "method": "POST",
    "path": "/api/public/v1/todos/{todo}/return",
    "inputSchema": {
      "type": "object",
      "properties": {
        "todo": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "body": {
          "type": "object",
          "additionalProperties": false,
          "required": [
            "expectedRevision"
          ],
          "properties": {
            "expectedRevision": {
              "type": "integer",
              "minimum": 1
            },
            "note": {
              "type": "string",
              "maxLength": 2000,
              "description": "\u9000\u56de\u6642\u53ef\u9644\u539f\u56e0"
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            },
            "reply": {
              "type": "string",
              "maxLength": 2000,
              "description": "\u5b8c\u6210\u6216\u9000\u56de\u6642\u9078\u586b\u7684\u7559\u8a00, \u8207\u72c0\u614b\u540c\u4ea4\u6613\u4fdd\u5b58"
            }
          }
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "todo",
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "comment_todo": {
    "operationId": "commentPublicTodo",
    "method": "POST",
    "path": "/api/public/v1/todos/{todo}/comments",
    "inputSchema": {
      "type": "object",
      "properties": {
        "todo": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "body": {
          "type": "object",
          "additionalProperties": false,
          "required": [
            "expectedRevision",
            "content"
          ],
          "properties": {
            "expectedRevision": {
              "type": "integer",
              "minimum": 1
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            },
            "content": {
              "type": "string",
              "minLength": 1,
              "maxLength": 2000
            }
          }
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "todo",
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  },
  "forward_todo": {
    "operationId": "forwardPublicTodo",
    "method": "POST",
    "path": "/api/public/v1/todos/{todo}/forward",
    "inputSchema": {
      "type": "object",
      "properties": {
        "todo": {
          "type": "string",
          "pattern": "^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
        },
        "body": {
          "type": "object",
          "additionalProperties": false,
          "required": [
            "expectedRevision",
            "assigneeId"
          ],
          "properties": {
            "expectedRevision": {
              "type": "integer",
              "minimum": 1
            },
            "requestId": {
              "type": "string",
              "format": "uuid"
            },
            "assigneeId": {
              "type": "string",
              "maxLength": 100
            },
            "reply": {
              "type": "string",
              "maxLength": 2000
            }
          }
        },
        "confirmation": {
          "type": "string",
          "maxLength": 2000
        }
      },
      "required": [
        "todo",
        "body"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    }
  }
};
