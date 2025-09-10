<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_sentiments\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\analyze_ai_sentiments\Service\SentimentsBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Batch form for sentiments analysis.
 */
final class SentimentsBatchForm extends FormBase {

  /**
   * Default chunk size for batch processing.
   */
  public const CHUNK_SIZE_DEFAULT = 5;

  public function __construct(
    private readonly SentimentsBatchService $batchService,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
          $container->get('analyze_ai_sentiments.batch_service'),
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'analyze_ai_sentiments_batch';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#markup' => $this->t('<p>Analyze content for sentiments metrics. Results are cached to improve performance. Only published content will be analyzed.</p>'),
    ];

    $available_bundles = $this->batchService->getAvailableEntityBundles();

    if (empty($available_bundles)) {
      $configure_url = Url::fromRoute('analyze.analyze_settings');
      $form['no_bundles'] = [
        '#markup' => $this->t('<p>No content types have sentiments analysis enabled. Please <a href="@url">configure the Analyze module</a> first.</p>', [
          '@url' => $configure_url->toString(),
        ]),
      ];
      return $form;
    }

    $form['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content Types'),
      '#description' => $this->t('Select which content types to analyze for sentiments.'),
      '#options' => $available_bundles,
      '#required' => TRUE,
    ];

    $form['force_refresh'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force re-analysis'),
      '#description' => $this->t('Re-analyze content even if recent results exist. This will replace all cached results.'),
    ];

    $form['chunk_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Chunk Size'),
      '#description' => $this->t('Number of entities to process in each batch.'),
      '#default_value' => static::CHUNK_SIZE_DEFAULT,
      '#min' => 1,
      '#max' => 50,
      '#access' => in_array('administrator', $this->currentUser()->getRoles()),
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('Maximum number of entities to analyze (0 for no limit).'),
      '#default_value' => 100,
      '#min' => 0,
      '#max' => 10000,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Start Sentiments Analysis'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $selected_types = array_filter($values['entity_types']);

    $entities = $this->batchService->getEntitiesForAnalysis(
          $selected_types,
          (bool) $values['force_refresh'],
          (int) $values['limit']
      );

    if (empty($entities)) {
      $this->messenger()->addWarning($this->t('No entities found for analysis.'));
      return;
    }

    $chunk_size = $form_state->hasValue('chunk_size') && in_array('administrator', $this->currentUser()->getRoles()) ?
      (int) $values['chunk_size']
      : static::CHUNK_SIZE_DEFAULT
      ;
    $total_entities = count($entities);
    $batch = [
      'title' => $this->t('Analyzing @count entities for sentiments', ['@count' => $total_entities]),
      'operations' => [],
      'finished' => [static::class, 'batchFinished'],
      'progressive' => TRUE,
    ];

    // Process in chunks of the specified size for better performance and memory management.
    $chunks = array_chunk($entities, $chunk_size);
    foreach ($chunks as $chunk) {
      $batch['operations'][] = [
        [$this->batchService, 'processBatch'],
        [$chunk, (bool) $values['force_refresh'], $total_entities],
      ];
    }

    batch_set($batch);
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   The batch results.
   * @param array $operations
   *   The batch operations.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    if ($success) {
      $processed = $results['processed'] ?? 0;
      \Drupal::messenger()->addStatus(\Drupal::translation()->formatPlural(
            $processed,
            'Successfully analyzed @count entity for sentiments.',
            'Successfully analyzed @count entities for sentiments.',
            ['@count' => $processed]
        ));

      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          \Drupal::messenger()->addError($error);
        }
      }
    }
    else {
      \Drupal::messenger()->addError(t('Sentiments analysis batch processing failed.'));
    }
  }

}
