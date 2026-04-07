# AI Sentiments Analysis

AI-powered multi-dimensional text analysis measuring trust, objectivity,
audience targeting, and reading levels.

## Features

- **Four Analysis Dimensions**: Trust & Credibility, Objectivity & Bias,
  Audience Vibe Check, CEFR Reading Level
- **Flexible Configuration**: Add, remove, reorder sentiments dimensions via UI
- **Content Type Control**: Enable/disable specific analysis types per content
  type
- **Visual Feedback**: Gauge displays with clear progression indicators
- **Analyze Framework Integration**: Consistent reporting across analysis tools

## Requirements

- [Analyze](https://www.drupal.org/project/analyze) framework
- [AI](https://www.drupal.org/project/ai) module with configured provider

## Installation

```bash
composer require drupal/analyze_ai_sentiments
drush en analyze_ai_sentiments
```

## Configuration

### Basic Setup
1. Configure AI provider at `/admin/config/ai/providers`
2. Configure sentiments dimensions at `/admin/config/analyze/sentiments`
3. Enable per content type at `/admin/config/content/analyze-settings`
4. Configure permissions at
   `/admin/people/permissions#module-analyze_ai_sentiments`

### Content Type Configuration
Two configuration methods:

#### Per Content Type Settings
- Go to `/admin/structure/types/manage/{content-type}`
- Find "Sentiments Analysis" vertical tab
- Enable/disable specific sentiments for this content type

#### Global Analyze Settings
- Go to `/admin/config/content/analyze-settings`
- Find "AI Sentiments Analysis" section
- Enable/disable analyzer for specific content types

## Analysis Dimensions

### Trust & Credibility
Measures how authoritative vs promotional the content appears.

### Objectivity & Bias
Evaluates balance between opinion-based and fact-based content.

### Audience Vibe Check
Identifies generational targeting (Gen Z to Boomer).

### CEFR Reading Level
Assesses language proficiency requirements (A1 Beginner to C2 Proficient).

## Display

Results shown as gauges with:
- Clear progression indicators
- Relevant min/mid/max labels for each dimension
- Simple visual assessment for quick content evaluation

## Batch Processing

Batch analysis is available through the centralized Analyze batch system:

- **Admin UI**: Navigate to Administration > Configuration > Content > Batch
  Analysis (`/admin/config/content/analyze-batch`), select "AI Sentiments
  Analysis" and your desired content types.
- **Drush CLI**: `drush analyze:batch --analyzers=analyze_ai_sentiments_analyzer`

See the [Analyze module documentation](https://www.drupal.org/project/analyze)
for full batch command options including `--types`, `--limit`, and `--force`.

## Development

### Docker Commands
```bash
# Lint code
docker compose run --rm drupal-lint

# Check deprecations
docker compose run --rm drupal-check

# Auto-fix issues
docker compose run --rm drupal-lint-auto-fix
```

### Pre-commit Hooks
Install with `./scripts/setup-hooks.sh`:
- Automatically runs `drupal-lint-auto-fix` on commits
- Blocks commits with unfixable lint issues
- Ensures consistent code quality

To bypass in emergencies:
```bash
git commit --no-verify -m "emergency commit"
```
