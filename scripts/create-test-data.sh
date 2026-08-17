#!/usr/bin/env bash
set -euo pipefail

# Create test data for Cattr end-to-end validation
# Simplified version that works with the current API

API_BASE="http://127.0.0.1"
ADMIN_EMAIL="admin@tracking.pinnaclemisr.com"
ADMIN_PASSWORD="CattrAdmin#2025!"

TEST_USER_EMAIL="testuser@cattr.local"
TEST_USER_PASSWORD="TestPass#2025!"
TEST_USER_NAME="Test User"

PROJECT_NAME="Test Project"
TASK_NAME="Sample Task"

echo "=== Creating Cattr test data ==="
echo

# Step 1: Admin login
echo "1. Logging in as admin..."
ADMIN_RESPONSE=$(curl -s -X POST "$API_BASE/api/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}")

ADMIN_TOKEN=$(echo "$ADMIN_RESPONSE" | jq -r '.data.access_token // empty')
if [[ -z "$ADMIN_TOKEN" ]]; then
  echo "ERROR: Admin login failed" >&2
  exit 1
fi
echo "✓ Admin logged in"
echo

# Step 2: Get admin info to extract company_id
echo "2. Fetching admin info..."
ADMIN_INFO=$(curl -s "$API_BASE/api/auth/me" \
  -H "Authorization: Bearer $ADMIN_TOKEN")

COMPANY_ID=$(echo "$ADMIN_INFO" | jq -r '.data.company_id // 1')
echo "✓ Company ID: $COMPANY_ID"
echo

# Step 3: Create test user
echo "3. Creating test user..."
USER_RESPONSE=$(curl -s -X POST "$API_BASE/api/users" \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d "{\"email\":\"$TEST_USER_EMAIL\",\"full_name\":\"$TEST_USER_NAME\",\"password\":\"$TEST_USER_PASSWORD\"}")

TEST_USER_ID=$(echo "$USER_RESPONSE" | jq -r '.data.id // empty')
if [[ -z "$TEST_USER_ID" ]]; then
  echo "Checking if user already exists..."
  USER_LIST=$(curl -s "$API_BASE/api/users" \
    -H "Authorization: Bearer $ADMIN_TOKEN")
  TEST_USER_ID=$(echo "$USER_LIST" | jq -r ".data[] | select(.email==\"$TEST_USER_EMAIL\").id // empty")
  
  if [[ -z "$TEST_USER_ID" ]]; then
    echo "ERROR: Could not create or find test user" >&2
    echo "Response: $USER_RESPONSE" >&2
    exit 1
  fi
  echo "✓ Test user already exists (ID: $TEST_USER_ID)"
else
  echo "✓ Test user created (ID: $TEST_USER_ID)"
fi
echo

# Step 4: Create project
echo "4. Creating project..."
PROJECT_RESPONSE=$(curl -s -X POST "$API_BASE/api/projects" \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d "{\"name\":\"$PROJECT_NAME\"}")

PROJECT_ID=$(echo "$PROJECT_RESPONSE" | jq -r '.data.id // empty')
if [[ -z "$PROJECT_ID" ]]; then
  echo "Checking if project already exists..."
  PROJECT_LIST=$(curl -s "$API_BASE/api/projects" \
    -H "Authorization: Bearer $ADMIN_TOKEN")
  PROJECT_ID=$(echo "$PROJECT_LIST" | jq -r ".data[] | select(.name==\"$PROJECT_NAME\").id // empty")
  
  if [[ -z "$PROJECT_ID" ]]; then
    echo "ERROR: Could not create or find project" >&2
    echo "Response: $PROJECT_RESPONSE" >&2
    exit 1
  fi
  echo "✓ Project already exists (ID: $PROJECT_ID)"
else
  echo "✓ Project created (ID: $PROJECT_ID)"
fi
echo

# Step 5: Get status types for active status
echo "5. Fetching status types..."
STATUS_RESPONSE=$(curl -s "$API_BASE/api/statuses" \
  -H "Authorization: Bearer $ADMIN_TOKEN")

ACTIVE_STATUS=$(echo "$STATUS_RESPONSE" | jq -r '.data[] | select(.active==true).id | first // 1')
echo "✓ Using status ID: $ACTIVE_STATUS"
echo

# Step 6: Create task
echo "6. Creating task..."
TASK_RESPONSE=$(curl -s -X POST "$API_BASE/api/tasks" \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d "{\"name\":\"$TASK_NAME\",\"project_id\":$PROJECT_ID,\"assigned_to\":$TEST_USER_ID,\"status_id\":$ACTIVE_STATUS}")

TASK_ID=$(echo "$TASK_RESPONSE" | jq -r '.data.id // empty')
if [[ -z "$TASK_ID" ]]; then
  echo "Checking if task already exists..."
  TASK_LIST=$(curl -s "$API_BASE/api/tasks?project_id=$PROJECT_ID" \
    -H "Authorization: Bearer $ADMIN_TOKEN")
  TASK_ID=$(echo "$TASK_LIST" | jq -r ".data[] | select(.name==\"$TASK_NAME\").id // empty")
  
  if [[ -z "$TASK_ID" ]]; then
    echo "ERROR: Could not create or find task" >&2
    echo "Response: $TASK_RESPONSE" >&2
    exit 1
  fi
  echo "✓ Task already exists (ID: $TASK_ID)"
else
  echo "✓ Task created (ID: $TASK_ID)"
fi
echo

echo "=== Test data setup complete ==="
echo
echo "✅ Ready for end-to-end testing!"
echo
echo "Summary:"
echo "  Company ID:       $COMPANY_ID"
echo "  Test User ID:     $TEST_USER_ID"
echo "  Test User Email:  $TEST_USER_EMAIL"
echo "  Test User Pass:   $TEST_USER_PASSWORD"
echo "  Project ID:       $PROJECT_ID ($PROJECT_NAME)"
echo "  Task ID:          $TASK_ID ($TASK_NAME)"
echo
echo "Next steps:"
echo "  1. Install the official Cattr desktop client"
echo "  2. When asked for server URL, enter: http://172.16.70.66/api"
echo "  3. Log in with:"
echo "     Email:    $TEST_USER_EMAIL"
echo "     Password: $TEST_USER_PASSWORD"
echo "  4. You should see '$TASK_NAME' in the task list"
echo "  5. Start tracking the task"
echo "  6. Log in to the web UI as admin and view the report"
