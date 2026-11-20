<?php

namespace Drupal\analyze_ai_sentiments\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for analyze_ai_sentiments.
 */
class AnalyzeAiSentimentsViewsHooks {
  use StringTranslationTrait;
  /**
   * @file
   * Provide views data for analyze_ai_sentiments.module.
   */

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData() {
    $data = [];
    $data['analyze_ai_sentiments_results']['table']['group'] = $this->t('Sentiments Analysis');
    $data['analyze_ai_sentiments_results']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Sentiments Analysis Results'),
      'help' => $this->t('AI sentiments analysis scores for content entities.'),
    ];
    // ID field.
    $data['analyze_ai_sentiments_results']['id'] = [
      'title' => $this->t('Analysis ID'),
      'help' => $this->t('The unique ID of the analysis result.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    // Entity type field.
    $data['analyze_ai_sentiments_results']['entity_type'] = [
      'title' => $this->t('Entity Type'),
      'help' => $this->t('The type of entity that was analyzed.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    // Entity ID field with relationship to content.
    $data['analyze_ai_sentiments_results']['entity_id'] = [
      'title' => $this->t('Entity ID'),
      'help' => $this->t('The ID of the entity that was analyzed.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'relationship' => [
        'id' => 'standard',
        'base' => 'node_field_data',
        'base field' => 'nid',
        'field' => 'entity_id',
        'label' => $this->t('Content'),
        'title' => $this->t('Content'),
        'help' => $this->t('The content entity that was analyzed.'),
      ],
    ];
    // Entity revision ID field.
    $data['analyze_ai_sentiments_results']['entity_revision_id'] = [
      'title' => $this->t('Entity Revision ID'),
      'help' => $this->t('The revision ID of the entity that was analyzed.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    // Language code field.
    $data['analyze_ai_sentiments_results']['langcode'] = [
      'title' => $this->t('Language'),
      'help' => $this->t('The language of the analyzed content.'),
      'field' => [
        'id' => 'language',
      ],
      'filter' => [
        'id' => 'language',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'language',
      ],
    ];
    // Sentiments ID field.
    $data['analyze_ai_sentiments_results']['sentiments_id'] = [
      'title' => $this->t('Sentiments Type'),
      'help' => $this->t('The sentiments metric identifier (e.g., "positivity", "engagement").'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'analyze_select',
        'options_callback' => 'analyze_ai_sentiments.storage:getSentimentOptions',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    // Sentiments score field.
    $data['analyze_ai_sentiments_results']['score'] = [
      'title' => $this->t('Sentiments Score'),
      'help' => $this->t('The sentiments score (-1.00 to +1.00).'),
      'field' => [
        'id' => 'numeric',
        'float' => TRUE,
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    // Content hash field.
    $data['analyze_ai_sentiments_results']['content_hash'] = [
      'title' => $this->t('Content Hash'),
      'help' => $this->t('The SHA256 hash of the analyzed content.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    // Config hash field.
    $data['analyze_ai_sentiments_results']['config_hash'] = [
      'title' => $this->t('Config Hash'),
      'help' => $this->t('The MD5 hash of the sentiments configuration.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    // Analysis timestamp field.
    $data['analyze_ai_sentiments_results']['analyzed_timestamp'] = [
      'title' => $this->t('Analysis Date'),
      'help' => $this->t('The date and time when the analysis was performed.'),
      'field' => [
        'id' => 'date',
        'click sortable' => TRUE,
      ],
      'filter' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
    ];
    return $data;
  }

}
