# PRADO Forum Extension Database Schema

## Overview
This document describes the database schema for a PRADO Forum extension supporting both MySQL and PostgreSQL databases. The schema includes tables for users, forums, categories, threads, posts, views, likes, saves, moderation, roles, and feeds.

## Tables

### 1. Users Table (`users`)
Stores user information for the forum.

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | INT (Primary Key, Auto Increment) | Unique user identifier |
| `username` | VARCHAR(50) | Unique username |
| `email` | VARCHAR(100) | User's email address |
| `password_hash` | VARCHAR(255) | Hashed password |
| `first_name` | VARCHAR(50) | User's first name |
| `last_name` | VARCHAR(50) | User's last name |
| `avatar_url` | VARCHAR(255) | URL to user's avatar |
| `signature` | TEXT | User's signature |
| `registration_date` | DATETIME | When user registered |
| `last_login` | DATETIME | When user last logged in |
| `is_active` | TINYINT(1) | Whether user account is active |
| `is_banned` | TINYINT(1) | Whether user is banned |
| `banned_reason` | TEXT | Reason for banning |
| `created_at` | TIMESTAMP | Record creation timestamp |
| `updated_at` | TIMESTAMP | Record update timestamp |

### 2. Categories Table (`categories`)
Stores forum categories.

| Field | Type | Description |
|-------|------|-------------|
| `category_id` | INT (Primary Key, Auto Increment) | Unique category identifier |
| `name` | VARCHAR(100) | Category name |
| `description` | TEXT | Category description |
| `sort_order` | INT | Display order of categories |
| `is_active` | TINYINT(1) | Whether category is active |
| `created_at` | TIMESTAMP | Record creation timestamp |
| `updated_at` | TIMESTAMP | Record update timestamp |

### 3. Forums Table (`forums`)
Stores individual forums within categories.

| Field | Type | Description |
|-------|------|-------------|
| `forum_id` | INT (Primary Key, Auto Increment) | Unique forum identifier |
| `category_id` | INT (Foreign Key) | Reference to category |
| `name` | VARCHAR(100) | Forum name |
| `description` | TEXT | Forum description |
| `sort_order` | INT | Display order of forums |
| `is_active` | TINYINT(1) | Whether forum is active |
| `thread_count` | INT | Number of threads in forum |
| `post_count` | INT | Number of posts in forum |
| `last_post_id` | INT | ID of last post |
| `last_post_user_id` | INT | ID of user who made last post |
| `last_post_date` | DATETIME | Date of last post |
| `created_at` | TIMESTAMP | Record creation timestamp |
| `updated_at` | TIMESTAMP | Record update timestamp |

### 4. Threads Table (`threads`)
Stores forum threads.

| Field | Type | Description |
|-------|------|-------------|
| `thread_id` | INT (Primary Key, Auto Increment) | Unique thread identifier |
| `forum_id` | INT (Foreign Key) | Reference to forum |
| `user_id` | INT (Foreign Key) | Thread creator |
| `title` | VARCHAR(255) | Thread title |
| `is_pinned` | TINYINT(1) | Whether thread is pinned |
| `is_locked` | TINYINT(1) | Whether thread is locked |
| `view_count` | INT | Number of times thread viewed |
| `reply_count` | INT | Number of replies |
| `is_approved` | TINYINT(1) | Whether thread is approved |
| `created_at` | TIMESTAMP | Thread creation timestamp |
| `updated_at` | TIMESTAMP | Thread update timestamp |
| `last_post_date` | DATETIME | Date of last post in thread |
| `last_post_user_id` | INT | ID of user who made last post |

### 5. Posts Table (`posts`)
Stores individual posts within threads.

| Field | Type | Description |
|-------|------|-------------|
| `post_id` | INT (Primary Key, Auto Increment) | Unique post identifier |
| `thread_id` | INT (Foreign Key) | Reference to thread |
| `user_id` | INT (Foreign Key) | Post creator |
| `content` | TEXT | Post content |
| `is_first_post` | TINYINT(1) | Whether this is the first post in thread |
| `is_approved` | TINYINT(1) | Whether post is approved |
| `created_at` | TIMESTAMP | Post creation timestamp |
| `updated_at` | TIMESTAMP | Post update timestamp |
| `edited_at` | TIMESTAMP | When post was edited |
| `edited_by` | INT | User who edited the post |

### 6. Views Table (`views`)
Tracks thread views.

| Field | Type | Description |
|-------|------|-------------|
| `view_id` | INT (Primary Key, Auto Increment) | Unique view identifier |
| `thread_id` | INT (Foreign Key) | Reference to thread |
| `user_id` | INT (Foreign Key) | User who viewed |
| `viewed_at` | TIMESTAMP | When user viewed thread |

### 7. Likes Table (`likes`)
Tracks user likes for posts.

| Field | Type | Description |
|-------|------|-------------|
| `like_id` | INT (Primary Key, Auto Increment) | Unique like identifier |
| `post_id` | INT (Foreign Key) | Reference to post |
| `user_id` | INT (Foreign Key) | User who liked |
| `created_at` | TIMESTAMP | When like was created |

### 8. Saves Table (`saves`)
Tracks user saved posts.

| Field | Type | Description |
|-------|------|-------------|
| `save_id` | INT (Primary Key, Auto Increment) | Unique save identifier |
| `post_id` | INT (Foreign Key) | Reference to post |
| `user_id` | INT (Foreign Key) | User who saved |
| `saved_at` | TIMESTAMP | When post was saved |

### 9. Moderation Table (`moderation`)
Tracks moderation actions.

| Field | Type | Description |
|-------|------|-------------|
| `moderation_id` | INT (Primary Key, Auto Increment) | Unique moderation identifier |
| `user_id` | INT (Foreign Key) | Moderator who performed action |
| `target_type` | VARCHAR(20) | Type of target (thread, post, user) |
| `target_id` | INT | ID of target |
| `action` | VARCHAR(50) | Type of moderation action |
| `reason` | TEXT | Reason for moderation |
| `created_at` | TIMESTAMP | When action occurred |

### 10. Roles Table (`roles`)
Stores user roles.

| Field | Type | Description |
|-------|------|-------------|
| `role_id` | INT (Primary Key, Auto Increment) | Unique role identifier |
| `name` | VARCHAR(50) | Role name |
| `description` | TEXT | Role description |
| `is_active` | TINYINT(1) | Whether role is active |
| `created_at` | TIMESTAMP | Record creation timestamp |

### 11. User Roles Table (`user_roles`)
Many-to-many relationship between users and roles.

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | INT (Foreign Key) | Reference to user |
| `role_id` | INT (Foreign Key) | Reference to role |

### 12. Feeds Table (`feeds`)
Tracks RSS feeds.

| Field | Type | Description |
|-------|------|-------------|
| `feed_id` | INT (Primary Key, Auto Increment) | Unique feed identifier |
| `title` | VARCHAR(255) | Feed title |
| `url` | VARCHAR(255) | Feed URL |
| `description` | TEXT | Feed description |
| `last_updated` | DATETIME | When feed was last updated |
| `is_active` | TINYINT(1) | Whether feed is active |
| `created_at` | TIMESTAMP | Record creation timestamp |

## Database Compatibility Notes

### For MySQL:
- All tables use `ENGINE=InnoDB`
- Timestamps default to `CURRENT_TIMESTAMP`
- Auto increment fields start at 1
- Primary keys are defined with `PRIMARY KEY`

### For PostgreSQL:
- All tables use `SERIAL` for auto-increment
- Timestamps default to `NOW()`
- Primary keys defined with `PRIMARY KEY`
- Sequences are used for auto-increment

## Indexes
- All foreign key fields have indexes for performance
- Unique indexes on `username` and `email` in users table
- Composite indexes on common query fields (e.g., `forum_id`, `created_at` in threads table)