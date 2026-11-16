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
  }
};
