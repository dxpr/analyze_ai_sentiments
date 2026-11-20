<?php

/**
 * @file
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\analyze_ai_sentiments\Hook\AnalyzeAiSentimentsViewsHooks;

/**
 * @file
 * Provide views data for analyze_ai_sentiments.module.
 */

/**
 * Implements hook_views_data().
 */
#[LegacyHook]
function analyze_ai_sentiments_views_data() {
  return \Drupal::service(AnalyzeAiSentimentsViewsHooks::class)->viewsData();
}
