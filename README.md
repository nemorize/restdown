# restdown
Lightweight headless CMS that works with Markdown and GitHub.<br />
Restdown supports Jekyll-like posts structure and formats, and manages it with Git.<br />
All contents are stored in a GitHub repository, and you can sync your contents with GitHub webhook.

## Requirements
- PHP 8.1 or higher
- SQLite3 and PDO SQLite extension
- Composer
- Git

## Installation

### 1. Clone this repository and install dependencies.
```bash
git clone https://github.com/nemorize/restdown
cd restdown
composer install
```

### 2. Create a new GitHub repository to store your contents.

### 3. Set up your .env file.
```dotenv
# APP_DEBUG="true" to print out some debug messages, or "false" to hide them.
APP_DEBUG="true"

# GITHUB_URL is the URL of your GitHub repository.
# Your PHP process should have read/write access to the repository.
# If the repository is private, you should use a personal access token.
GITHUB_URL="https://username:token@github.com/username/repo_of_course_everything_should_be_changed"

# GITHUB_SECRET is the secret of your GitHub webhook.
GITHUB_SECRET=""

# MARKDOWN_ROOT is the path to the directory where your markdown files are stored.
# This path is relative to the root directory of restdown.
MARKDOWN_ROOT="./markdowns"
```

### 4. Set up your web server to serve the `public` directory.

### 5. Set up your GitHub webhook.
- Payload URL: https://your.domain.com/webhook
- Secret: (same as GITHUB_SECRET in .env file)
- Event types: Just the push event should be enough.

## API Specification

### Get posts
```http request
GET /posts?offset=0&limit=10&query=keyword

# offset: offset of posts (default: 0)
# limit: limit of posts (default: 10)
# query: search keyword (optional)
```

#### Response
```json
{
  "success": true,
  "posts": [
    {
      "slug": "slug-of-post",
      "title": "Title of post",
      "categories": [
        "category1",
        "category2"
      ],
      "tags": [
        "tag1",
        "tag2"
      ],
      "createdAt": 1672531200,
      "updatedAt": 1672531200,
      "extras": {},
      "content": "Content of post as HTML format"
    }
  ]
}
```

### Get post
```http request
GET /posts/{slug}

# slug: slug of post
```

#### Response
```json
{
  "success": true,
  "post": {
    "slug": "slug-of-post",
    "title": "Title of post",
    "categories": [
      "category1",
      "category2"
    ],
    "tags": [
      "tag1",
      "tag2"
    ],
    "createdAt": 1672531200,
    "updatedAt": 1672531200,
    "extras": {},
    "content": "Content of post as HTML format"
  }
}
```