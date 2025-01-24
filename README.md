## CONTENTS OF THIS FILE

- Introduction
- Requirements
- Installation
- Configuration
- Maintainers

## INTRODUCTION

The Analyze AI Sentiment module provides AI-powered text analysis capabilities for Drupal 10 content, measuring various aspects like trust, objectivity, audience targeting, and reading levels.

The primary use case for this module is to:

- **Analyze** content using AI processing
- **Score** text content across multiple dimensions:
  - Overall Sentiment (from Negative to Positive)
  - Engagement Level (from Passive to Interactive)
  - Trust & Credibility (from Promotional to Authoritative)
  - Objectivity (from Subjective to Objective)
  - Technical Complexity (from Basic to Complex)
- **Guide** content creators with instant AI feedback

Goals:

- A comprehensive AI text analysis solution for Drupal content
- A stable, maintainable API for AI-powered content evaluation
- A pluggable architecture for different AI providers
- Integration with the Analyze framework for consistent reporting

## REQUIREMENTS

This module requires the following modules:

- Analyze (drupal/analyze)
- AI (drupal/ai)

## INSTALLATION

1. If your site is managed via Composer, use:
   ```composer require "drupal/analyze_ai_sentiment"```
   Otherwise, copy the module to your Drupal installation's modules directory.

2. Enable the 'Analyze AI Sentiment' module in 'Extend'.
   (/admin/modules)

3. Configure permissions for content analysis.
   (/admin/people/permissions#module-analyze_ai_sentiment)

## CONFIGURATION

### Basic Setup
- Configure global sentiment analysis settings at `/admin/config/analyze/sentiment`
- Configure AI provider settings at `/admin/config/analyze/ai`

### Content Type Configuration
You can enable/disable and configure sentiments per content type:

1. Through the content type edit form:
   - Go to `/admin/structure/types/manage/[content-type]`
   - Click on the "Analyze" tab
   - Find the "Sentiment Analysis" section
   - Enable/disable specific sentiment metrics

2. Through individual content:
   - View any content piece
   - Look for the Analyze tab or section
   - Find the "Sentiment Analysis" settings
   - Enable/disable specific sentiment metrics

The settings can be configured globally at `/admin/config/analyze/sentiment` and then enabled/disabled per content type or piece of content.

### Analysis Metrics
The module comes with several predefined analysis metrics that can be managed at `/admin/config/analyze/sentiment`:

- **Overall Sentiment**: Evaluate content from Negative to Positive
- **Engagement Level**: Measure interaction from Passive to Interactive
- **Trust & Credibility**: Evaluate content from Promotional to Authoritative
- **Objectivity**: Measure content from Subjective to Objective
- **Technical Complexity**: Assess from Basic to Complex

Each metric:
- Can be enabled/disabled per content type
- Has customizable labels for minimum, middle, and maximum values
- Can be reordered using drag-and-drop
- Returns scores between -1.0 and +1.0
- Can be added or removed through the UI

### Display
- Results are shown as gauges with the configured labels
- Scores are normalized to show clear progression from minimum to maximum values
- The first enabled metric is used for summary displays
- Detailed view shows all enabled metrics

## MAINTAINERS

Current maintainers:
- Jurriaan Roelofs - https://www.drupal.org/u/jurriaanroelofs

This project is sponsored by:
- DXPR - https://www.drupal.org/node/2303425

For bug reports and feature requests, please use the project's issue queue at:
https://www.drupal.org/project/issues/analyze_ai_sentiment 