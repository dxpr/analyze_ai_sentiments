<?php

/**
 * @file
 * Provide views data for analyze_ai_sentiment.module.
 */

/**
 * Implements hook_views_data().
 */
function analyze_ai_sentiment_views_data() {
  $data = [];

  $data['analyze_ai_sentiment_results']['table']['group'] = t('Sentiment Analysis');
  $data['analyze_ai_sentiment_results']['table']['base'] = [
    'field' => 'id',
    'title' => t('Sentiment Analysis Results'),
    'help' => t('AI sentiment analysis scores for content entities.'),
  ];

  // ID field.
  $data['analyze_ai_sentiment_results']['id'] = [
    'title' => t('Analysis ID'),
    'help' => t('The unique ID of the analysis result.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  // Entity type field.
  $data['analyze_ai_sentiment_results']['entity_type'] = [
    'title' => t('Entity Type'),
    'help' => t('The type of entity that was analyzed.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Entity ID field with relationship to content.
  $data['analyze_ai_sentiment_results']['entity_id'] = [
    'title' => t('Entity ID'),
    'help' => t('The ID of the entity that was analyzed.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
    'relationship' => [
      'id' => 'standard',
      'base' => 'node_field_data',
      'base field' => 'nid',
      'field' => 'entity_id',
      'label' => t('Content'),
      'title' => t('Content'),
      'help' => t('The content entity that was analyzed.'),
    ],
  ];

  // Entity revision ID field.
  $data['analyze_ai_sentiment_results']['entity_revision_id'] = [
    'title' => t('Entity Revision ID'),
    'help' => t('The revision ID of the entity that was analyzed.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  // Language code field.
  $data['analyze_ai_sentiment_results']['langcode'] = [
    'title' => t('Language'),
    'help' => t('The language of the analyzed content.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Sentiment ID field.
  $data['analyze_ai_sentiment_results']['sentiment_id'] = [
    'title' => t('Sentiment Type'),
    'help' => t('The sentiment metric identifier (e.g., "positivity", "engagement").'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Sentiment score field.
  $data['analyze_ai_sentiment_results']['score'] = [
    'title' => t('Sentiment Score'),
    'help' => t('The sentiment score (-1.00 to +1.00).'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  // Content hash field.
  $data['analyze_ai_sentiment_results']['content_hash'] = [
    'title' => t('Content Hash'),
    'help' => t('The SHA256 hash of the analyzed content.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Config hash field.
  $data['analyze_ai_sentiment_results']['config_hash'] = [
    'title' => t('Config Hash'),
    'help' => t('The MD5 hash of the sentiment configuration.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Analysis timestamp field.
  $data['analyze_ai_sentiment_results']['analyzed_timestamp'] = [
    'title' => t('Analysis Date'),
    'help' => t('The date and time when the analysis was performed.'),
    'field' => [
      'id' => 'date',
      'click sortable' => TRUE,
    ],
    'filter' => ['id' => 'date'],
    'sort' => ['id' => 'date'],
    'argument' => ['id' => 'date'],
  ];

  return $data;
}