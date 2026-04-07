<?php

namespace Drupal\analyze_ai_sentiments\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\analyze_ai_sentiments\Service\SentimentsStorageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Configure sentiments analysis settings.
 */
class SentimentsSettingsForm extends FormBase {
  /**
   * The sentiments storage service.
   */
  protected SentimentsStorageService $sentimentstorage;

  /**
   * The current user service.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a SentimentsSettingsForm object.
   *
   * @param \Drupal\analyze_ai_sentiments\Service\SentimentsStorageService $sentiments_storage
   *   The sentiments storage service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(
    SentimentsStorageService $sentiments_storage,
    AccountProxyInterface $current_user,
  ) {
    $this->sentimentstorage = $sentiments_storage;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @var static */
    return new self(
          $container->get('analyze_ai_sentiments.storage'),
          $container->get('current_user')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analyze_ai_sentiments_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var array<string, mixed> $form */
    $sentiments = $this->sentimentstorage->getAllSentiments();

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['sentiments-description']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Configure the sentiments metrics used to analyze content. Each sentiments has a scale from -1.0 to +1.0 with customizable labels.'),
      ],
    ];

    // Add link to reports page if user has permission.
    if ($this->currentUser->hasPermission('access site reports')) {
      $reports_url = Url::fromRoute('view.ai_sentiments_analysis_results.page_1');
      if ($reports_url->access()) {
        $form['actions_top'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['form-actions']],
          '#weight' => -10,
          'report_link' => [
            '#type' => 'link',
            '#title' => $this->t('View reports'),
            '#url' => $reports_url,
            '#attributes' => [
              'class' => ['button', 'button--small', 'button--primary'],
            ],
          ],
        ];
      }
    }

    $form['table'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['sentiments-table-container']],
    ];

    $form['sentiments'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Sentiments'),
        $this->t('Labels'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'sentiments-weight',
        ],
      ],
    ];

    // Sort sentiments by weight.
    uasort($sentiments, function ($a, $b) {
        return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    // Add existing sentiments to the table.
    foreach ($sentiments as $id => $sentiment) {
      // Add safety check for corrupted data.
      if (!is_array($sentiment) || !isset($sentiment['label'])) {
        continue;
      }
      $form['sentiments'][$id] = [
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
          '#attributes' => ['class' => ['sentiments-weight']],
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('analyze_ai_sentiments.delete_sentiments', ['sentiments_id' => $id]),
              'attributes' => [
                'class' => ['button', 'button--danger', 'button--small'],
              ],
            ],
          ],
        ],
      ];
    }

    // Help text for drag-and-drop.
    if (!empty($sentiments)) {
      $form['table_help'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Drag and drop rows to reorder the sentiments. This order will be reflected in the analysis display.'),
        '#attributes' => ['class' => ['sentiments-help-text', 'description']],
        '#weight' => 5,
      ];
    }

    // Add form actions.
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save changes'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var array<string, mixed> $form */
    // Save each sentiment using the storage service.
    $sentiments_to_save = $form_state->getValue('sentiments') ?: [];

    foreach ($sentiments_to_save as $id => $values) {
      if (!isset($values['labels']['min_label'])) {
        continue;
      }

      $this->sentimentstorage->saveSentiment(
        $id,
        $values['label'],
        $values['labels']['min_label'],
        $values['labels']['mid_label'],
        $values['labels']['max_label'],
        (int) $values['weight']
      );
    }

    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }

}
