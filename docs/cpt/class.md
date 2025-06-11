# Class Custom Post Type

## Registration

```php
register_post_type('class', [
  // ...registration args...
]);
```

## Supported Fields

| Field        | Type   | Description           |
|--------------|--------|-----------------------|
| title        | string | Class name            |
| school_id    | int    | Linked school         |

## Meta Fields

| Meta Key     | Type   | Description           |
|--------------|--------|-----------------------|
| _class_code  | string | Internal code         |

## Relationships

- Each class belongs to one school.
- Each class can have many students and teachers.

## REST API Integration

- Exposed at `/wp-json/ham/v1/classes`
- CRUD supported via REST API.
