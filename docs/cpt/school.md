# School Custom Post Type

## Registration

```php
register_post_type('school', [
  'labels' => [...],
  'public' => false,
  'show_ui' => true,
  ...
]);
```

## Supported Fields

| Field        | Type   | Description           |
|--------------|--------|-----------------------|
| title        | string | School name           |
| principal_id | int    | Linked principal user |

## Meta Fields

| Meta Key     | Type   | Description           |
|--------------|--------|-----------------------|
| _school_code | string | Internal code         |

## Relationships

- Each school can have many classes.
- Each school has one principal.

## REST API Integration

- Exposed at `/wp-json/ham/v1/schools`
- CRUD supported via REST API.
