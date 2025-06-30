#!/bin/bash

# Setup script for Git hooks
# This script installs the pre-commit hook for Drupal code quality

set -e

echo "🔧 Setting up Git hooks for Drupal code quality..."

# Get the repository root
REPO_ROOT=$(git rev-parse --show-toplevel)
HOOKS_DIR="$REPO_ROOT/.git/hooks"
GITHOOKS_DIR="$REPO_ROOT/.githooks"

# Check if we're in a git repository
if [ ! -d "$REPO_ROOT/.git" ]; then
    echo "❌ Not in a Git repository"
    exit 1
fi

# Check if .githooks directory exists
if [ ! -d "$GITHOOKS_DIR" ]; then
    echo "❌ .githooks directory not found"
    echo "   Expected: $GITHOOKS_DIR"
    exit 1
fi

# Install pre-commit hook
echo "📋 Installing pre-commit hook..."

if [ -f "$HOOKS_DIR/pre-commit" ]; then
    echo "⚠️  Existing pre-commit hook found"
    echo "   Backing up to pre-commit.backup"
    cp "$HOOKS_DIR/pre-commit" "$HOOKS_DIR/pre-commit.backup"
fi

# Copy and make executable
cp "$GITHOOKS_DIR/pre-commit" "$HOOKS_DIR/pre-commit"
chmod +x "$HOOKS_DIR/pre-commit"

echo "✅ Pre-commit hook installed successfully!"
echo ""
echo "🎯 What happens now:"
echo "   • Every commit will run 'drupal-lint-auto-fix'"
echo "   • If auto-fix resolves all issues, commit proceeds"
echo "   • If issues remain, commit is blocked with details"
echo "   • Manual fixes required for unresolvable issues"
echo ""
echo "🚀 To test the hook:"
echo "   git commit -m 'test commit'"
echo ""
echo "🔧 To bypass hook (emergency only):"
echo "   git commit --no-verify -m 'emergency commit'"
echo ""

# Test Docker setup
echo "🧪 Testing Docker setup..."
if command -v docker-compose &> /dev/null; then
    DOCKER_CMD="docker-compose"
elif command -v docker &> /dev/null; then
    DOCKER_CMD="docker compose"
else
    echo "❌ Docker not found. Please install Docker to use the hooks."
    exit 1
fi

if $DOCKER_CMD ps &> /dev/null; then
    echo "✅ Docker is running and accessible"
else
    echo "⚠️  Docker may not be running. Please ensure Docker is started."
fi

echo ""
echo "🎉 Setup complete! Your commits will now be automatically checked for Drupal code quality."