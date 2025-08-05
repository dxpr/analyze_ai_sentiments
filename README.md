## CONTENTS OF THIS FILE

- Introduction
- Requirements
- Installation
- Configuration
- Maintainers

## INTRODUCTION

The AI Sentiment Analysis module provides AI-powered text analysis capabilities
for Drupal content, measuring various aspects like trust, objectivity,
audience targeting, and reading levels.

The primary use case for this module is to:

- **Analyze** content using AI processing
- **Score** text content across multiple dimensions:
  - Trust & Credibility (from Promotional to Authoritative)
  - Objectivity & Bias (from Opinion-Based to Fact-Based)
  - Audience Vibe Check (from Gen Z to Boomer)
  - CEFR Reading Level (from A1 Beginner to C2 Proficient)
- **Guide** content creators with instant AI feedback

Goals:

- A comprehensive AI text analysis solution for Drupal content
- A stable, maintainable API for AI-powered content evaluation
- Integration with the Analyze framework for consistent reporting
- Standardized measurements for content evaluation

## REQUIREMENTS

This module requires the following modules:

- Analyze (drupal/analyze)
- AI (drupal/ai)

## INSTALLATION

1. If your site is managed via Composer, use:
   ```composer require "drupal/analyze_ai_sentiment"```
   Otherwise, copy the module to your Drupal installation's modules directory.

2. Enable the 'AI Sentiment Analysis' module in 'Extend'.
   (/admin/modules)

3. Configure permissions for content analysis.
   (/admin/people/permissions#module-analyze_ai_sentiment)

### Docker Commands

This module uses Docker to ensure consistent development and testing
environments. Here are the key Docker commands you can use:

#### Linting Drupal Code

To run the Drupal linter:

```bash
docker compose run --rm drupal-lint
```

This command checks your Drupal code for adherence to coding standards and
best practices.

#### Running Drupal Deprecation and Analysis Checks

To perform Drupal deprecation and analysis checks:

```bash
docker compose run --rm drupal-check
```

This command analyzes your code for usage of deprecated Drupal APIs and other
potential issues.

#### Auto-fixing Drupal Code

To automatically fix some coding standard issues:

```bash
docker compose run --rm drupal-lint-auto-fix
```

This command will attempt to automatically fix coding standard violations in
your Drupal code.

#### Environment Variables

The `DRUPAL_RECOMMENDED_PROJECT` environment variable is already defined in
the process. You don't need to specify it when running the commands.

These Docker commands help maintain code quality and compatibility across
different Drupal versions.

Make sure to run these checks before submitting pull requests or merging
changes into the main branch.

#### Pre-commit Hooks

To automatically enforce code quality, install the pre-commit hook:

```bash
./scripts/setup-hooks.sh
```

This sets up a Git pre-commit hook that:
- Automatically runs `drupal-lint-auto-fix` on every commit
- Blocks commits if any unfixable lint issues remain
- Ensures consistent code quality across all contributions

To bypass the hook in emergencies:
```bash
git commit --no-verify -m "emergency commit"
```

## CONFIGURATION

### Basic Setup
- Configure AI provider settings at `/admin/config/ai/providers`
- Configure and order sentiments at `/admin/config/analyze/sentiment`
- Enable/disable the analyzer per content type at
  `/admin/config/content/analyze-settings`

### Content Type Configuration
You can configure sentiment analysis per content type in two ways:

1. Through the content type settings:
   - Go to `/admin/structure/types/manage/{content-type}`
   - Find the "Sentiment Analysis" vertical tab
   - Enable/disable specific sentiments for this content type

2. Through the analyze settings:
   - Go to `/admin/config/content/analyze-settings`
   - Find the "AI Sentiment Analysis" section
   - Enable/disable the analyzer for specific content types

### Analysis Metrics
The module evaluates content across four key dimensions:

- Trust & Credibility: Measures how authoritative vs promotional the
  content appears
- Objectivity & Bias: Evaluates the balance between opinion and
  fact-based content
- Audience Vibe Check: Identifies the generational targeting of the content
- CEFR Reading Level: Assesses the content's language proficiency requirements

### Display
- Results are shown as gauges with clear progression
- Each dimension shows relevant min/mid/max labels
- Simple visual indicators for quick content assessment

## MAINTAINERS

Current maintainers:
- Jurriaan Roelofs - https://www.drupal.org/u/jurriaanroelofs

This project is sponsored by:
- DXPR - https://www.drupal.org/node/2303425

For bug reports and feature requests, please use the project's issue queue at:
https://www.drupal.org/project/issues/analyze_ai_sentiment 
