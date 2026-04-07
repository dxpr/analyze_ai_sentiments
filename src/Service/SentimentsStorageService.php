<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_sentiments\Service;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Service for storing and retrieving sentiments analysis results.
 */
final class SentimentsStorageService {
  use DependencySerializationTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RendererInterface $renderer,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Gets the cached sentiments scores for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the scores for.
   *
   * @return array<string, float>
   *   Array of sentiments_id => score pairs.
   */
  public function getScores(EntityInterface $entity): array {
    $content_hash = $this->generateContentHash($entity);
    $config_hash = $this->generateConfigHash();

    $results = $this->database->select('analyze_ai_sentiments_results', 'r')
      ->fields('r', ['sentiments_id', 'score'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->condition('langcode', $entity->language()->getId())
      ->condition('content_hash', $content_hash)
      ->condition('config_hash', $config_hash)
      ->execute()
      ->fetchAllKeyed();

    return array_map('floatval', $results);
  }

  /**
   * Saves sentiments scores for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the scores are for.
   * @param array<string, float> $scores
   *   Array of sentiments_id => score pairs.
   */
  public function saveScores(EntityInterface $entity, array $scores): void {
    foreach ($scores as $sentiments_id => $score) {
      $this->saveScore($entity, $sentiments_id, $score);
    }
  }

  /**
   * Saves a single sentiment score for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the score is for.
   * @param string $sentiments_id
   *   The sentiment ID.
   * @param float $score
   *   The sentiment score (-1.0 to +1.0).
   */
  public function saveScore(EntityInterface $entity, string $sentiments_id, float $score): void {
    // Ensure score is within valid range.
    $score = max(-1.0, min(1.0, $score));

    $this->database->merge('analyze_ai_sentiments_results')
      ->keys([
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
        'sentiments_id' => $sentiments_id,
        'langcode' => $entity->language()->getId(),
      ])
      ->fields([
        'entity_revision_id' => $entity instanceof RevisionableInterface ? $entity->getRevisionId() : 0,
        'score' => $score,
        'content_hash' => $this->generateContentHash($entity),
        'config_hash' => $this->generateConfigHash(),
        'analyzed_timestamp' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Deletes all stored scores for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to delete scores for.
   */
  public function deleteScores(EntityInterface $entity): void {
    $this->database->delete('analyze_ai_sentiments_results')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute();
  }

  /**
   * Invalidates all cached results due to configuration changes.
   */
  public function invalidateConfigCache(): void {
    // Delete all records with old config hash.
    $current_hash = $this->generateConfigHash();
    $this->database->delete('analyze_ai_sentiments_results')
      ->condition('config_hash', $current_hash, '!=')
      ->execute();
  }

  /**
   * Gets statistics about stored analysis results.
   *
   * @return array<string, int>
   *   Array with count statistics.
   */
  public function getStatistics(): array {
    $query = $this->database->select('analyze_ai_sentiments_results', 'r');
    $query->addExpression('COUNT(*)', 'total_results');
    $query->addExpression('COUNT(DISTINCT entity_id)', 'unique_entities');
    $query->addExpression('COUNT(DISTINCT sentiments_id)', 'unique_sentiments');
    $query->addExpression('MIN(analyzed_timestamp)', 'oldest_analysis');
    $query->addExpression('MAX(analyzed_timestamp)', 'newest_analysis');

    $result = $query->execute()->fetchAssoc();

    return [
      'total_results' => (int) $result['total_results'],
      'unique_entities' => (int) $result['unique_entities'],
      'unique_sentiments' => (int) $result['unique_sentiments'],
      'oldest_analysis' => $result['oldest_analysis'] ? (int) $result['oldest_analysis'] : 0,
      'newest_analysis' => $result['newest_analysis'] ? (int) $result['newest_analysis'] : 0,
    ];
  }

  /**
   * Gets average scores by sentiments type.
   *
   * @return array<string, float>
   *   Array of sentiments_id => average_score pairs.
   */
  public function getAverageScores(): array {
    $query = $this->database->select('analyze_ai_sentiments_results', 'r');
    $query->fields('r', ['sentiments_id']);
    $query->addExpression('AVG(score)', 'average_score');
    $query->groupBy('sentiments_id');
    $results = $query->execute()->fetchAllKeyed();

    return array_map('floatval', $results);
  }

  /**
   * Generates a configuration hash for sentiments settings.
   *
   * @return string
   *   The MD5 hash of the sentiments configuration.
   */
  private function generateConfigHash(): string {
    $config = $this->configFactory->get('analyze_ai_sentiments.settings');
    $sentiments = $config->get('sentiments') ?? [];

    // Sort to ensure consistent hashing.
    ksort($sentiments);

    return hash('md5', serialize($sentiments));
  }

  /**
   * Generates a content hash for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate a hash for.
   *
   * @return string
   *   The SHA256 hash of the entity content.
   */
  private function generateContentHash(EntityInterface $entity): string {
    $content = $this->getEntityContent($entity);
    return hash('sha256', $content);
  }

  /**
   * Extracts clean text content from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to extract content from.
   *
   * @return string
   *   The cleaned text content.
   */
  private function getEntityContent(EntityInterface $entity): string {
    // Use the entity's own language, not the current UI language.
    $langcode = $entity->language()->getId();

    // Render the entity in default view mode.
    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    $view = $view_builder->view($entity, 'default', $langcode);
    $rendered = $this->renderer->renderPlain($view);

    // Convert to string and clean up.
    $content = (string) $rendered;

    // Strip HTML tags and normalize whitespace.
    $content = strip_tags($content);
    $content = str_replace('&nbsp;', ' ', $content);
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);

    return $content;
  }

  /**
   * Gets sentiment options for Views filter.
   *
   * @return array<string, string>
   *   Array of sentiments_id => label pairs for use in select widgets.
   */
  public function getSentimentOptions(): array {
    $sentiments = $this->getAllSentiments();
    $options = [];
    foreach ($sentiments as $sentiment_id => $sentiment_data) {
      $options[$sentiment_id] = $sentiment_data['label'] ?? $sentiment_id;
    }
    return $options;
  }

  /**
   * Gets all configured sentiments.
   *
   * @return array<string, array<string, mixed>>
   *   Array of sentiment configurations keyed by sentiment ID.
   */
  public function getAllSentiments(): array {
    $config = $this->configFactory->get('analyze_ai_sentiments.settings');
    $sentiments = $config->get('sentiments');

    // If no sentiments configured, return defaults.
    if (empty($sentiments)) {
      return $this->getDefaultSentiments();
    }

    // Validate structure - each value should be an array with required keys.
    $valid_sentiments = [];
    foreach ($sentiments as $id => $sentiment) {
      if (is_array($sentiment) && isset($sentiment['label'], $sentiment['min_label'], $sentiment['mid_label'], $sentiment['max_label'])) {
        $valid_sentiments[$id] = $sentiment;
      }
    }

    // If no valid sentiments, return defaults.
    if (empty($valid_sentiments)) {
      return $this->getDefaultSentiments();
    }

    return $valid_sentiments;
  }

  /**
   * Gets a specific sentiment by ID.
   *
   * @param string $sentiment_id
   *   The sentiment ID.
   *
   * @return array<string, mixed>|null
   *   The sentiment configuration or NULL if not found.
   */
  public function getSentiment(string $sentiment_id): ?array {
    $sentiments = $this->getAllSentiments();
    return $sentiments[$sentiment_id] ?? NULL;
  }

  /**
   * Saves a sentiment configuration.
   *
   * @param string $sentiment_id
   *   The sentiment ID.
   * @param string $label
   *   The sentiment label.
   * @param string $min_label
   *   The minimum range label.
   * @param string $mid_label
   *   The middle range label.
   * @param string $max_label
   *   The maximum range label.
   * @param int $weight
   *   The sentiment weight for ordering.
   */
  public function saveSentiment(
    string $sentiment_id,
    string $label,
    string $min_label,
    string $mid_label,
    string $max_label,
    int $weight,
  ): void {
    $config = $this->configFactory->getEditable('analyze_ai_sentiments.settings');
    $existing_sentiments = $config->get('sentiments') ?: [];

    $existing_sentiments[$sentiment_id] = [
      'id' => $sentiment_id,
      'label' => $label,
      'min_label' => $min_label,
      'mid_label' => $mid_label,
      'max_label' => $max_label,
      'weight' => $weight,
    ];

    $config->set('sentiments', $existing_sentiments)->save();
    $this->invalidateConfigCache();
  }

  /**
   * Deletes a sentiment configuration.
   *
   * @param string $sentiment_id
   *   The sentiment ID to delete.
   */
  public function deleteSentiment(string $sentiment_id): void {
    $config = $this->configFactory->getEditable('analyze_ai_sentiments.settings');
    $sentiments = $config->get('sentiments') ?: [];

    if (isset($sentiments[$sentiment_id])) {
      unset($sentiments[$sentiment_id]);
      $config->set('sentiments', $sentiments)->save();
      $this->invalidateConfigCache();
    }
  }

  /**
   * Checks if a sentiment ID exists.
   *
   * @param string $sentiment_id
   *   The sentiment ID to check.
   *
   * @return bool
   *   TRUE if the sentiment exists, FALSE otherwise.
   */
  public function sentimentExists(string $sentiment_id): bool {
    return $this->getSentiment($sentiment_id) !== NULL;
  }

  /**
   * Gets the default sentiment configurations.
   *
   * @return array<string, array<string, mixed>>
   *   Array of default sentiment configurations.
   */
  private function getDefaultSentiments(): array {
    return [
      'overall' => [
        'id' => 'overall',
        'label' => 'Overall Sentiments',
        'min_label' => 'Negative',
        'mid_label' => 'Neutral',
        'max_label' => 'Positive',
        'weight' => 0,
      ],
      'engagement' => [
        'id' => 'engagement',
        'label' => 'Engagement Level',
        'min_label' => 'Passive',
        'mid_label' => 'Balanced',
        'max_label' => 'Interactive',
        'weight' => 1,
      ],
      'trust' => [
        'id' => 'trust',
        'label' => 'Trust/Credibility',
        'min_label' => 'Promotional',
        'mid_label' => 'Balanced',
        'max_label' => 'Authoritative',
        'weight' => 2,
      ],
      'objectivity' => [
        'id' => 'objectivity',
        'label' => 'Objectivity',
        'min_label' => 'Subjective',
        'mid_label' => 'Mixed',
        'max_label' => 'Objective',
        'weight' => 3,
      ],
      'complexity' => [
        'id' => 'complexity',
        'label' => 'Technical Complexity',
        'min_label' => 'Basic',
        'mid_label' => 'Moderate',
        'max_label' => 'Complex',
        'weight' => 4,
      ],
    ];
  }

}
