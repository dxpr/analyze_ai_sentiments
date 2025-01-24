<?php

namespace Drupal\analyze_ai_sentiment\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure sentiment analysis settings.
 */
class SentimentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analyze_ai_sentiment_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['analyze_ai_sentiment.settings'];
  }

  /**
   * Gets the default sentiment configurations.
   *
   * @return array
   *   Array of default sentiment configurations.
   */
  public function getDefaultSentiments(): array {
    return [
      'sentiment' => [
        'label' => $this->t('Overall Sentiment'),
        'min_label' => $this->t('Negative'),
        'mid_label' => $this->t('Neutral'),
        'max_label' => $this->t('Positive'),
        'weight' => 0,
      ],
      'engagement' => [
        'label' => $this->t('Engagement Level'),
        'min_label' => $this->t('Passive'),
        'mid_label' => $this->t('Balanced'),
        'max_label' => $this->t('Interactive'),
        'weight' => 1,
      ],
      'trust' => [
        'label' => $this->t('Trust/Credibility'),
        'min_label' => $this->t('Promotional'),
        'mid_label' => $this->t('Balanced'),
        'max_label' => $this->t('Authoritative'),
        'weight' => 2,
      ],
      'objectivity' => [
        'label' => $this->t('Objectivity'),
        'min_label' => $this->t('Subjective'),
        'mid_label' => $this->t('Mixed'),
        'max_label' => $this->t('Objective'),
        'weight' => 3,
      ],
      'complexity' => [
        'label' => $this->t('Technical Complexity'),
        'min_label' => $this->t('Basic'),
        'mid_label' => $this->t('Moderate'),
        'max_label' => $this->t('Complex'),
        'weight' => 4,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('analyze_ai_sentiment.settings');
    $sentiments = $config->get('sentiments') ?: $this->getDefaultSentiments();

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['sentiment-description']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Configure the sentiment metrics used to analyze content. Each sentiment has a scale from -1.0 to +1.0 with customizable labels.'),
      ],
    ];

    $form['table'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['sentiment-table-container']],
    ];

    $form['table']['sentiments'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Sentiment'),
        $this->t('Labels'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'sentiment-weight',
        ],
      ],
    ];

    // Sort sentiments by weight
    uasort($sentiments, function ($a, $b) {
      return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    // Add existing sentiments to the table
    foreach ($sentiments as $id => $sentiment) {
      $form['table']['sentiments'][$id] = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
        'label' => [
          '#type' => 'textfield',
          '#title' => $this->t('Label'),
          '#title_display' => 'invisible',
          '#default_value' => $sentiment['label'],
          '#required' => TRUE,
        ],
        'labels' => [
          '#type' => 'details',
          '#title' => $this->t('Range Labels'),
          'min_label' => [
            '#type' => 'textfield',
            '#title' => $this->t('Minimum'),
            '#default_value' => $sentiment['min_label'],
            '#required' => TRUE,
          ],
          'mid_label' => [
            '#type' => 'textfield',
            '#title' => $this->t('Middle'),
            '#default_value' => $sentiment['mid_label'],
            '#required' => TRUE,
          ],
          'max_label' => [
            '#type' => 'textfield',
            '#title' => $this->t('Maximum'),
            '#default_value' => $sentiment['max_label'],
            '#required' => TRUE,
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $sentiment['weight'],
          '#attributes' => ['class' => ['sentiment-weight']],
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => \Drupal\Core\Url::fromRoute('analyze_ai_sentiment.delete_sentiment', ['sentiment_id' => $id]),
              'attributes' => [
                'class' => ['button', 'button--danger', 'button--small'],
              ],
            ],
          ],
        ],
      ];
    }

    // Help text for drag-and-drop
    if (!empty($sentiments)) {
      $form['table_help'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Drag and drop rows to reorder the sentiments. This order will be reflected in the analysis display.'),
        '#attributes' => ['class' => ['sentiment-help-text', 'description']],
        '#weight' => 5,
      ];
    }

    $form = parent::buildForm($form, $form_state);
    
    // Improve the save button
    $form['actions']['submit']['#value'] = $this->t('Save changes');
    $form['actions']['submit']['#attributes']['class'][] = 'button--primary';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $sentiments = [];
    foreach ($form_state->getValue('sentiments') as $id => $values) {
      $sentiments[$id] = [
        'label' => $values['label'],
        'min_label' => $values['labels']['min_label'],
        'mid_label' => $values['labels']['mid_label'],
        'max_label' => $values['labels']['max_label'],
        'weight' => $values['weight'],
      ];
    }

    $this->config('analyze_ai_sentiment.settings')
      ->set('sentiments', $sentiments)
      ->save();

    parent::submitForm($form, $form_state);
  }

} 