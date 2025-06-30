<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_sentiment\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Service for batch processing sentiment analysis.
 */
final class SentimentBatchService {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SentimentStorageService $storage,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    private readonly DefaultPluginManager $analyzePluginManager,
  ) {}

  /**
   * Gets entities that need sentiment analysis.
   *
   * @param array<string> $entity_bundles
   *   Array of entity_type:bundle strings.
   * @param bool $force_refresh
   *   Whether to include entities with existing analysis.
   * @param int $limit
   *   Maximum number of entities to return.
   *
   * @return array<array<string, string>>
   *   Array of entity info arrays.
   */
  public function getEntitiesForAnalysis(array $entity_bundles, bool $force_refresh = FALSE, int $limit = 0): array {
    $entities = [];

    foreach ($entity_bundles as $entity_bundle) {
      [$entity_type_id, $bundle] = explode(':', $entity_bundle);

      $query = $this->entityTypeManager->getStorage($entity_type_id)
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $bundle);

      // Only include published content.
      if ($entity_type_id === 'node') {
        $query->condition('status', 1);
      }

      if (!$force_refresh) {
        // Only include entities that need analysis (no valid cache).
        $analyzed_ids = $this->getAnalyzedEntityIds($entity_type_id, $bundle);
        if (!empty($analyzed_ids)) {
          $query->condition($entity_type_id === 'node' ? 'nid' : 'id', $analyzed_ids, 'NOT IN');
        }
      }

      if ($limit > 0) {
        $remaining = $limit - count($entities);
        if ($remaining <= 0) {
          break;
        }
        $query->range(0, $remaining);
      }

      $ids = $query->execute();

      foreach ($ids as $id) {
        $entities[] = [
          'entity_type' => $entity_type_id,
          'entity_id' => $id,
          'bundle' => $bundle,
        ];
      }
    }

    return $entities;
  }

  /**
   * Processes a batch of entities for sentiment analysis.
   *
   * @param array<array<string, string>> $entities
   *   Array of entity info.
   * @param bool $force_refresh
   *   Whether to force fresh analysis.
   * @param int $total_entities
   *   Total number of entities being processed across all batches.
   * @param array<string, mixed> $context
   *   Batch context.
   */
  public function processBatch(array $entities, bool $force_refresh, int $total_entities, array &$context): void {
    if (!isset($context['sandbox']['total_entities'])) {
      $context['sandbox']['total_entities'] = $total_entities;
      $context['results']['processed'] = 0;
      $context['results']['errors'] = [];
    }

    try {
      $analyzer = $this->analyzePluginManager
        ->createInstance('ai_sentiment_analyzer');

      foreach ($entities as $entity_data) {
        try {
          $entity = $this->entityTypeManager
            ->getStorage($entity_data['entity_type'])
            ->load($entity_data['entity_id']);

          if ($entity) {
            if ($force_refresh) {
              $this->storage->deleteScores($entity);
            }

            // Capture any output to prevent JSON corruption.
            ob_start();
            $analyzer->renderSummary($entity);
            ob_end_clean();

            $context['results']['processed']++;
          }
        }
        catch (\Exception $e) {
          $context['results']['errors'][] = $this->t('Error processing @type @id: @message', [
            '@type' => $entity_data['entity_type'],
            '@id' => $entity_data['entity_id'],
            '@message' => $e->getMessage(),
          ])->render();
        }

      }
    }
    catch (\Exception $e) {
      $context['results']['errors'][] = $this->t('Batch processing error: @message', [
        '@message' => $e->getMessage(),
      ])->render();
    }

    $context['message'] = $this->t('Processed @current of @max entities...', [
      '@current' => $context['results']['processed'],
      '@max' => $context['sandbox']['total_entities'],
    ])->render();

    // Calculate progress based on total entities processed vs total entities.
    if ($context['sandbox']['total_entities'] > 0) {
      $context['finished'] = $context['results']['processed'] / $context['sandbox']['total_entities'];
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Gets available entity bundles that have sentiment analysis enabled.
   *
   * @return array<string, string>
   *   Array of entity_type:bundle => label pairs.
   */
  public function getAvailableEntityBundles(): array {
    $config = $this->configFactory->get('analyze.settings');
    $status = $config->get('status') ?? [];

    $options = [];
    foreach ($status as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle => $analyzers) {
        if (isset($analyzers['analyze_ai_sentiment_analyzer'])) {
          $bundle_info = $this->bundleInfo->getBundleInfo($entity_type_id);
          $label = $bundle_info[$bundle]['label'] ?? $bundle;
          $options["{$entity_type_id}:{$bundle}"] = "{$entity_type_id} - {$label}";
        }
      }
    }

    return $options;
  }

  /**
   * Gets entities that already have valid cached analysis.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return array<string>
   *   Array of entity IDs that have valid cached results.
   */
  private function getAnalyzedEntityIds(string $entity_type_id, string $bundle): array {
    // Get entities that have valid cached analysis.
    $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle);

    $all_ids = $query->execute();
    $analyzed_ids = [];

    foreach ($all_ids as $id) {
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
      if ($entity && !empty($this->storage->getScores($entity))) {
        $analyzed_ids[] = $id;
      }
    }

    return $analyzed_ids;
  }

}
