# PRADO Forum Extension Database Schema

This document describes the database schema for the PRADO Forum extension that supports both MySQL and PostgreSQL databases.

## Tables Overview

| Table Name      | Description                            |
|-----------------|----------------------------------------|
| users           | User information and profiles          |
| forums          | Forum structure and hierarchy          |
| categories      | Category grouping for forums           |
| threads         | Discussion threads                     |
| posts           | Individual forum posts                 |
| forum_views     | Tracks forum views                     |
| thread_views    | Tracks thread views                    |
| likes           | User likes for posts                   |
| saves           | User saved posts/threads               |
| moderators      | Forum moderator assignments            |
| roles           | User roles and permissions             |
| user_roles      | User role assignments                  |
| feeds           | RSS feed configuration                 |

## Detailed Table Structures

### users
| Field            | Type             | Description                           |
|------------------|------------------|---------------------------------------|
| id               | INT (AUTO)       | Primary key                           |
| username         | VARCHAR(255)     | Unique user name                      |
| email            | VARCHAR(255)     | Unique email address                  |
| password_hash    | VARCHAR(255)     | Password hash                         |
| first_name       | VARCHAR(100)     | First name                            |
| last_name        | VARCHAR(100)     | Last name                             |
| avatar_url       | VARCHAR(500)     | Avatar image URL                      |
| join_date        | DATETIME         | Join timestamp                        |
| last_login       | DATETIME         | Last login timestamp                  |
| is_active        | BOOLEAN          | Active status                          |
| is_banned        | BOOLEAN          | Banned status                          |
| bio              | TEXT             | User biography                         |
| location         | VARCHAR(255)     | Location                              |
| website          | VARCHAR(500)     | Website URL                           |
| signature        | TEXT             | Forum signature                        |
| posts_count      | INT              | Number of posts                       |
| reputation       | INT              | User reputation score                 |
| created_at       | TIMESTAMP        | Created timestamp                     |
| updated_at       | TIMESTAMP        | Updated timestamp                     |

### forums
| Field                | Type             | Description                              |
|----------------------|------------------|------------------------------------------|
| id                   | INT (AUTO)       | Primary key                              |
| name                 | VARCHAR(255)     | Forum name                               |
| description          | TEXT             | Description                              |
| parent_forum_id      | INT              | Parent forum reference                   |
| sort_order           | INT              | Sort order                               |
| is_active            | BOOLEAN          | Active status                            |
| post_count           | INT              | Number of posts                          |
| thread_count         | INT              | Number of threads                        |
| last_post_id         | INT              | Last post ID                             |
| last_post_date       | DATETIME         | Last post timestamp                      |
| last_post_user_id    | INT              | Last post user ID                        |
| created_at           | TIMESTAMP        | Created timestamp                        |
| updated_at           | TIMESTAMP        | Updated timestamp                        |

### categories
| Field        | Type             | Description                           |
|--------------|------------------|---------------------------------------|
| id           | INT (AUTO)       | Primary key                           |
| name         | VARCHAR(255)     | Category name                         |
| description  | TEXT             | Description                           |
| sort_order   | INT              | Sort order                            |
| is_active    | BOOLEAN          | Active status                         |
| forum_count  | INT              | Number of forums in category          |
| created_at   | TIMESTAMP        | Created timestamp                     |
| updated_at   | TIMESTAMP        | Updated timestamp                     |

### threads
| Field              | Type             | Description                           |
|--------------------|------------------|---------------------------------------|
| id                 | INT (AUTO)       | Primary key                           |
| forum_id           | INT              | Forum reference                       |
| user_id            | INT              | User who started thread               |
| title              | VARCHAR(255)     | Thread title                          |
| is_sticky          | BOOLEAN          | Sticky status                         |
| is_locked          | BOOLEAN          | Locked status                         |
| is_pinned          | BOOLEAN          | Pinned status                         |
| view_count         | INT              | View count                            |
| reply_count        | INT              | Reply count                           |
| first_post_id      | INT              | First post ID                         |
| last_post_id       | INT              | Last post ID                          |
| last_post_date     | DATETIME         | Last post timestamp                   |
| last_post_user_id  | INT              | User who made last post               |
| created_at         | TIMESTAMP        | Created timestamp                     |
| updated_at         | TIMESTAMP        | Updated timestamp                     |

### posts
| Field            | Type             | Description                           |
|------------------|------------------|---------------------------------------|
| id               | INT (AUTO)       | Primary key                           |
| thread_id        | INT              | Thread reference                      |
| user_id          | INT              | User who posted                       |
| content          | TEXT             | Post content                          |
| is_first_post    | BOOLEAN          | First post in thread                  |
| is_edited        | BOOLEAN          | Edited status                         |
| edit_reason      | TEXT             | Edit reason                           |
| created_at       | TIMESTAMP        | Created timestamp                     |
| updated_at       | TIMESTAMP        | Updated timestamp                     |

### likes
| Field        | Type             | Description                           |
|--------------|------------------|---------------------------------------|
| id           | INT (AUTO)       | Primary key                           |
| user_id      | INT              | User who liked                        |
| post_id      | INT              | Post that was liked                   |
| created_at   | TIMESTAMP        | Liked timestamp                       |

### saves
| Field        | Type             | Description                           |
|--------------|------------------|---------------------------------------|
| id           | INT (AUTO)       | Primary key                           |
| user_id      | INT              | User who saved                        |
| post_id      | INT              | Post that was saved                   |
| created_at   | TIMESTAMP        | Saved timestamp                       |

### moderators
| Field        | Type             | Description                           |
|--------------|------------------|---------------------------------------|
| id           | INT (AUTO)       | Primary key                           |
| user_id      | INT              | Moderator user ID                     |
| forum_id     | INT              | Forum that is moderated               |
| created_at   | TIMESTAMP        | Assigned timestamp                    |

### roles
| Field        | Type             | Description                           |
|--------------|------------------|---------------------------------------|
| id           | INT (AUTO)       | Primary key                           |
| name         | VARCHAR(100)     | Role name                             |
| description  | TEXT             | Role description                      |
| created_at   | TIMESTAMP        | Created timestamp                     |

### user_roles
| Field        | Type             | Description                           |
|--------------|------------------|---------------------------------------|
| id           | INT (AUTO)       | Primary key                           |
| user_id      | INT              | User ID                               |
| role_id      | INT              | Role ID                               |
| created_at   | TIMESTAMP        | Assigned timestamp                    |

### feeds
| Field        | Type             | Description                           |
|--------------|------------------|---------------------------------------|
| id           | INT (AUTO)       | Primary key                           |
| name         | VARCHAR(255)     | Feed name                             |
| url          | VARCHAR(500)     | Feed URL                              |
| type         | VARCHAR(50)      | Feed type (rss/atom)                  |
| is_active    | BOOLEAN          | Active status                         |
| created_at   | TIMESTAMP        | Created timestamp                     |
| updated_at   | TIMESTAMP        | Updated timestamp                     |

## Database Constraints

- All tables have primary keys (INT AUTO_INCREMENT)
- Foreign key constraints between related tables
- Unique constraints on username and email in users table
- Timestamp fields for created_at and updated_at
- Boolean flags for active states

## Support for MySQL and PostgreSQL

The schema is compatible with both MySQL and PostgreSQL with:
- Timestamp handling consistent across databases
- Integer primary key generation
- String field lengths optimized for both databases
- Boolean type support