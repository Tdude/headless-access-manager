# Schools API

## List Schools

**GET** `/wp-json/ham/v1/schools`

### Query Parameters
| Name     | Type | Required | Description      |
|----------|------|----------|------------------|
| page     | int  | No       | Page number      |
| per_page | int  | No       | Results per page |

### Example Request
```http
GET /wp-json/ham/v1/schools?page=1&per_page=10
```

### Example Response
```json
[
  {
    "id": 123,
    "name": "Example School",
    "principal_id": 456
  }
]
```

### Permissions
- Requires: `read` capability for schools

---

## Create School

**POST** `/wp-json/ham/v1/schools`

### Body Parameters
| Name | Type   | Required | Description   |
|------|--------|----------|---------------|
| name | string | Yes      | School name   |

### Example Request
```json
{
  "name": "New School"
}
```

### Example Response
```json
{
  "id": 124,
  "name": "New School"
}
```

### Permissions
- Requires: `edit_schools` capability

---

## Get Single School

**GET** `/wp-json/ham/v1/schools/{id}`

### Example Request
```http
GET /wp-json/ham/v1/schools/123
```

### Example Response
```json
{
  "id": 123,
  "name": "Example School",
  "principal_id": 456
}
```

---

## Update School

**PUT** `/wp-json/ham/v1/schools/{id}`

### Body Parameters
| Name | Type   | Required | Description   |
|------|--------|----------|---------------|
| name | string | No       | School name   |

### Example Request
```json
{
  "name": "Updated School Name"
}
```

### Example Response
```json
{
  "id": 123,
  "name": "Updated School Name"
}
```

### Permissions
- Requires: `edit_schools` capability

---

## Delete School

**DELETE** `/wp-json/ham/v1/schools/{id}`

### Example Request
```http
DELETE /wp-json/ham/v1/schools/123
```

### Example Response
```json
{
  "deleted": true,
  "id": 123
}
```

### Permissions
- Requires: `delete_schools` capability
