# Database Schema Documentation for PRADO Forum Extension

## Overview
This document provides documentation for the database schema used in the PRADO Forum Extension. The schema supports both MySQL and PostgreSQL databases with complete functionality for forum operations.

## Schema Structure

### Core Entities
- **Users**: Manage user accounts with authentication and profile information
- **Categories**: Organize forums into logical groups
- **Forums**: Contain threads within categories
- **Threads**: Represent individual discussions
- **Posts**: Contain content within threads
- **Views**: Track user thread views
- **Likes**: Track user likes for posts
- **Saves**: Allow users to save posts for later reading
- **Moderation**: Record all moderation actions
- **Roles**: Define user roles and permissions
- **Feeds**: Manage RSS feed integration

## Implementation Details

### Database Compatibility
The schema has been designed to work with:
- **MySQL 5.7+** (InnoDB engine)
- **PostgreSQL 10+**

### Key Features

#### User Management
- Complete user registration and authentication
- Profile information storage
- Account activation and banning
- Timestamps for account creation and updates

#### Forum Organization
- Hierarchical structure (Categories -> Forums -> Threads -> Posts)
- Sorting and organization capabilities
- Thread and post counts for forum statistics

#### Content Management
- Thread locking and pinning
- Post approval system
- First post identification
- Editing and modification tracking

#### User Interaction
- Thread view tracking
- Post likes system
- Saved posts functionality
- Comprehensive moderation logging

### Indexes and Performance

#### MySQL Indexes
- Primary keys automatically created
- Foreign key indexes for relationships
- Specialized indexes on frequently queried columns:
  - User activity (active, banned)
  - Category/forum sorting
  - Thread status (approved, pinned)
  - Date-based queries (created_at, last_post_date)

#### PostgreSQL Indexes
- All the same indexes as MySQL
- Additional composite indexes for optimal query performance
- Full support for PostgreSQL's advanced indexing features

### Data Types and Constraints

#### MySQL
- INT for primary keys with AUTO_INCREMENT
- VARCHAR with defined sizes for text fields
- TINYINT(1) for boolean values
- TIMESTAMP for date/time fields with default values
- Foreign key constraints with cascade deletes

#### PostgreSQL
- SERIAL for primary keys with auto-increment
- VARCHAR with defined sizes for text fields
- BOOLEAN for boolean values
- TIMESTAMP with NOW() defaults
- Foreign key constraints with CASCADE deletes
- UNIQUE constraints on username and email

## Relationships

### One-to-Many Relationships
- **Categories → Forums**: One category can contain many forums
- **Forums → Threads**: One forum can contain many threads  
- **Threads → Posts**: One thread can contain many posts
- **Users → Threads**: One user can create many threads
- **Users → Posts**: One user can create many posts
- **Users → Views**: One user can view many threads
- **Users → Likes**: One user can like many posts
- **Users → Saves**: One user can save many posts
- **Users → Moderation**: One user can perform many moderation actions

### Many-to-Many Relationships
- **Users ↔ Roles**: One user can have many roles, one role can be assigned to many users

## Migration Considerations

### MySQL Migration
- Supports InnoDB storage engine
- Use utf8mb4 charset for full Unicode support
- All foreign keys should have appropriate constraints

### PostgreSQL Migration  
- Uses SERIAL for auto-increment
- All indexes use PostgreSQL syntax
- Full support for data types and constraints

## Security Considerations

### Database Security
- Passwords stored as hashed values (no plain text passwords)
- Unique constraints on usernames and emails
- Foreign key relationships maintain data integrity
- Timestamps provide audit trail capabilities

### Access Control
- No hardcoded database credentials in schema
- Separate tables for different entity types
- Normalized design reduces data redundancy

## Future Extensions

The schema is designed to be extensible:
- Additional fields for user profiles can be added to users table
- New moderation actions can be added to moderation table
- Additional feed types can be supported in feeds table
- New relationship types can be added through additional junction tables