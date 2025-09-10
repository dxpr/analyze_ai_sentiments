<?php

/**
 * @file
 * Provide views data for analyze_ai_sentiments.module.
 */

/**
 * Implements hook_views_data().
 *
 * @phpstan-return array<string, mixed>
 */
function analyze_ai_sentiments_views_data(): array {
  $data = [];

  $data['analyze_ai_sentiments_results']['table']['group'] = t('Sentiments Analysis');
  $data['analyze_ai_sentiments_results']['table']['base'] = [
    'field' => 'id',
    'title' => t('Sentiments Analysis Results'),
    'help' => t('AI sentiments analysis scores for content entities.'),
  ];

  // ID field.
  $data['analyze_ai_sentiments_results']['id'] = [
    'title' => t('Analysis ID'),
    'help' => t('The unique ID of the analysis result.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  // Entity type field.
  $data['analyze_ai_sentiments_results']['entity_type'] = [
    'title' => t('Entity Type'),
    'help' => t('The type of entity that was analyzed.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Entity ID field with relationship to content.
  $data['analyze_ai_sentiments_results']['entity_id'] = [
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
  $data['analyze_ai_sentiments_results']['entity_revision_id'] = [
    'title' => t('Entity Revision ID'),
    'help' => t('The revision ID of the entity that was analyzed.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  // Language code field.
  $data['analyze_ai_sentiments_results']['langcode'] = [
    'title' => t('Language'),
    'help' => t('The language of the analyzed content.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Sentiments ID field.
  $data['analyze_ai_sentiments_results']['sentiments_id'] = [
    'title' => t('Sentiments Type'),
    'help' => t('The sentiments metric identifier (e.g., "positivity", "engagement").'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Sentiments score field.
  $data['analyze_ai_sentiments_results']['score'] = [
    'title' => t('Sentiments Score'),
    'help' => t('The sentiments score (-1.00 to +1.00).'),
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
  $data['analyze_ai_sentiments_results']['content_hash'] = [
    'title' => t('Content Hash'),
    'help' => t('The SHA256 hash of the analyzed content.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Config hash field.
  $data['analyze_ai_sentiments_results']['config_hash'] = [
    'title' => t('Config Hash'),
    'help' => t('The MD5 hash of the sentiments configuration.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Analysis timestamp field.
  $data['analyze_ai_sentiments_results']['analyzed_timestamp'] = [
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
