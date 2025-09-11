<?php

namespace Drupal\analyze_ai_sentiments\Plugin\Analyze;

use Drupal\Core\Entity\EntityInterface;
use Drupal\analyze\AnalyzePluginBase;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\Core\Link;
use Drupal\analyze_ai_sentiments\Service\SentimentsStorageService;

/**
 * A sentiments analyzer that uses AI to analyze content sentiments.
 *
 * @Analyze(
 *   id = "analyze_ai_sentiments_analyzer",
 *   label = @Translation("Sentiments Analysis"),
 *   description = @Translation("Analyzes the sentiments of content using AI.")
 * )
 */
final class AISentimentsAnalyzer extends AnalyzePluginBase {
  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null
   */
  protected ?ConfigFactoryInterface $configFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The prompt JSON decoder service.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected PromptJsonDecoderInterface $promptJsonDecoder;

  /**
   * The sentiments storage service.
   *
   * @var \Drupal\analyze_ai_sentiments\Service\SentimentsStorageService
   */
  protected SentimentsStorageService $storage;

  /**
   * Creates the plugin.
   *
   * @param array<string, mixed> $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array<string, mixed> $plugin_definition
   *   Plugin Definition.
   * @param \Drupal\analyze\HelperInterface $helper
   *   Analyze helper service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt JSON decoder service.
   * @param \Drupal\analyze_ai_sentiments\Service\SentimentsStorageService $storage
   *   The sentiments storage service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $helper,
    $currentUser,
    AiProviderPluginManager $aiProvider,
    ?ConfigFactoryInterface $config_factory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RendererInterface $renderer,
    protected LanguageManagerInterface $languageManager,
    MessengerInterface $messenger,
    PromptJsonDecoderInterface $promptJsonDecoder,
    SentimentsStorageService $storage,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $helper, $currentUser);
    $this->aiProvider = $aiProvider;
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->promptJsonDecoder = $promptJsonDecoder;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var static */
    return new self(
          $configuration,
          $plugin_id,
          $plugin_definition,
          $container->get('analyze.helper'),
          $container->get('current_user'),
          $container->get('ai.provider'),
          $container->get('config.factory'),
          $container->get('entity_type.manager'),
          $container->get('renderer'),
          $container->get('language_manager'),
          $container->get('messenger'),
          $container->get('ai.prompt_json_decode'),
          $container->get('analyze_ai_sentiments.storage'),
      );
  }

  /**
   * Get configured sentiments.
   *
   * @return array<string, mixed>
   *   Array of sentiments configurations.
   */
  protected function getConfiguredSentiments(): array {
    return $this->storage->getAllSentiments();
  }

  /**
   * Gets the enabled sentiments for an entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle ID.
   *
   * @return array<string, mixed>
   *   Array of enabled sentiments IDs.
   */
  protected function getEnabledSentiments(string $entity_type_id, ?string $bundle = NULL): array {
    if (!$this->isEnabledForEntityType($entity_type_id, $bundle)) {
      return [];
    }

    // Get settings from plugin_settings config.
    $plugin_settings_config = $this->configFactory->get('analyze.plugin_settings');
    $key = sprintf('%s.%s.%s', $entity_type_id, $bundle, $this->getPluginId());
    $settings = $plugin_settings_config->get($key) ?? [];

    // Get all available sentiments.
    $sentiments = $this->getConfiguredSentiments();

    $enabled = [];
    foreach ($sentiments as $id => $sentiment) {
      // If no settings exist yet, enable all sentiments by default.
      if (!isset($settings['sentiments'])) {
        $enabled[$id] = $sentiment;
      }
      // Otherwise check if explicitly enabled in settings.
      elseif (isset($settings['sentiments'][$id]) && $settings['sentiments'][$id]) {
        $enabled[$id] = $sentiment;
      }
    }

    // Sort enabled sentiments by weight.
    uasort($enabled, function ($a, $b) {
        return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    return $enabled;
  }

  /**
   * Creates a fallback status table.
   *
   * @param string $message
   *   The status message to display.
   *
   * @return array<string, mixed>
   *   The render array for the status table.
   */
  private function createStatusTable(string $message): array {
    // If this is the AI provider message and user has permission,
    // append the settings link.
    if ($message === 'No chat AI provider is configured for sentiments analysis.' && $this->currentUser->hasPermission('administer analyze settings')) {
      $link = Link::createFromRoute($this->t('Configure AI provider'), 'ai.settings_form');
      $message = $this->t('No chat AI provider is configured for sentiments analysis. @link', ['@link' => $link->toString()]);
    }

    return [
      '#theme' => 'analyze_table',
      '#table_title' => 'Sentiments Analysis',
      '#rows' => [
      [
        'label' => 'Status',
        'data' => $message,
      ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function renderSummary(EntityInterface $entity): array {
    $status_config = $this->configFactory->get('analyze.settings');
    $status = $status_config->get('status') ?? [];
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    if (!isset($status[$entity_type][$bundle][$this->getPluginId()])) {
      $settings_link = Link::createFromRoute($this->t('Enable sentiments analysis'), 'analyze.analyze_settings')->toString();
      return $this->createStatusTable($this->t('Sentiments analysis is not enabled for this content type. @link to configure content types.', ['@link' => $settings_link]));
    }

    $enabled_sentiments = $this->getEnabledSentiments($entity->getEntityTypeId(), $entity->bundle());
    if (empty($enabled_sentiments)) {
      $settings_link = Link::createFromRoute($this->t('Configure sentiments metrics'), 'analyze_ai_sentiments.settings')->toString();
      return $this->createStatusTable($this->t('No sentiments metrics are currently enabled. @link to select metrics to analyze.', ['@link' => $settings_link]));
    }

    // Try to get cached scores first.
    $scores = $this->storage->getScores($entity);

    // If no cached scores, perform analysis.
    if (empty($scores)) {
      $scores = $this->analyzeSentiments($entity);
      if (!empty($scores)) {
        $this->storage->saveScores($entity, $scores);
      }
    }

    // We'll just show the first enabled sentiments gauge if available.
    $sentiments = reset($enabled_sentiments);
    $id = key($enabled_sentiments);

    if (isset($scores[$id])) {
      // Convert -1 to +1 range to 0 to 1 for gauge.
      $gauge_value = ($scores[$id] + 1) / 2;

      return [
        '#theme' => 'analyze_gauge',
        '#caption' => $this->t('@label', ['@label' => $sentiments['label']]),
        '#range_min_label' => $this->t('@label', ['@label' => $sentiments['min_label']]),
        '#range_mid_label' => $this->t('@label', ['@label' => $sentiments['mid_label']]),
        '#range_max_label' => $this->t('@label', ['@label' => $sentiments['max_label']]),
        '#range_min' => -1,
        '#range_max' => 1,
        '#value' => $gauge_value,
        '#display_value' => sprintf('%+.1f', $scores[$id]),
      ];
    }

    // If no scores available, check if it's a provider issue or analysis
    // failure.
    if (!empty($content = $this->getHtml($entity))) {
      $ai_provider = $this->getAiProvider();
      if (!$ai_provider) {
        $ai_link = Link::createFromRoute($this->t('Configure AI provider'), 'ai.settings_form')->toString();
        return $this->createStatusTable($this->t('No chat AI provider is configured for sentiments analysis. @link to set up AI services.', ['@link' => $ai_link]));
      }
      else {
        return $this->createStatusTable($this->t('AI analysis failed to generate scores. Check logs for details or try again.'));
      }
    }

    return $this->createStatusTable($this->t('This content has no text available for sentiments analysis. Add content such as body text, fields, or descriptions to enable analysis.'));
  }

  /**
   * {@inheritdoc}
   */
  public function renderFullReport(EntityInterface $entity): array {
    $status_config = $this->configFactory->get('analyze.settings');
    $status = $status_config->get('status') ?? [];
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    if (!isset($status[$entity_type][$bundle][$this->getPluginId()])) {
      $settings_link = Link::createFromRoute($this->t('Enable sentiments analysis'), 'analyze.analyze_settings')->toString();
      return $this->createStatusTable($this->t('Sentiments analysis is not enabled for this content type. @link to configure content types.', ['@link' => $settings_link]));
    }

    $enabled_sentiments = $this->getEnabledSentiments($entity->getEntityTypeId(), $entity->bundle());
    if (empty($enabled_sentiments)) {
      $settings_link = Link::createFromRoute($this->t('Configure sentiments metrics'), 'analyze_ai_sentiments.settings')->toString();
      return $this->createStatusTable($this->t('No sentiments metrics are currently enabled. @link to select metrics to analyze.', ['@link' => $settings_link]));
    }

    // Try to get cached scores first.
    $scores = $this->storage->getScores($entity);

    // If no cached scores, perform analysis.
    if (empty($scores)) {
      $scores = $this->analyzeSentiments($entity);
      if (!empty($scores)) {
        $this->storage->saveScores($entity, $scores);
      }
    }

    // If no scores available but content exists, check if it's a provider
    // issue or analysis failure.
    if (empty($scores) && !empty($this->getHtml($entity))) {
      $ai_provider = $this->getAiProvider();
      if (!$ai_provider) {
        $ai_link = Link::createFromRoute($this->t('Configure AI provider'), 'ai.settings_form')->toString();
        return $this->createStatusTable($this->t('No chat AI provider is configured for sentiments analysis. @link to set up AI services.', ['@link' => $ai_link]));
      }
      else {
        return $this->createStatusTable($this->t('AI analysis failed to generate scores. Check logs for details or try again.'));
      }
    }

    // If no content available, show that message.
    if (empty($this->getHtml($entity))) {
      return $this->createStatusTable($this->t('This content has no text available for sentiments analysis. Add content such as body text, fields, or descriptions to enable analysis.'));
    }

    // Only build the gauge display if we have scores.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['analyze-sentiments-report'],
      ],
    ];

    foreach ($enabled_sentiments as $id => $sentiment) {
      if (isset($scores[$id])) {
        // Convert -1 to +1 range to 0 to 1 for gauge.
        $gauge_value = ($scores[$id] + 1) / 2;

        $build[$id] = [
          '#theme' => 'analyze_gauge',
          '#caption' => $this->t('@label', ['@label' => $sentiment['label']]),
          '#range_min_label' => $this->t('@label', ['@label' => $sentiment['min_label']]),
          '#range_mid_label' => $this->t('@label', ['@label' => $sentiment['mid_label']]),
          '#range_max_label' => $this->t('@label', ['@label' => $sentiment['max_label']]),
          '#range_min' => -1,
          '#range_max' => 1,
          '#value' => $gauge_value,
          '#display_value' => sprintf('%+.1f', $scores[$id]),
        ];
      }
      else {
        // Show analysis failure message for sentiments without scores.
        $build[$id] = [
          '#theme' => 'analyze_table',
          '#table_title' => $sentiments['label'],
          '#rows' => [
            [
              'label' => 'Status',
              'data' => $this->t('AI analysis failed to generate score. Check logs for details or try again.'),
            ],
          ],
        ];
      }
    }

    return $build;
  }

  /**
   * Helper to get the rendered entity content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to render.
   *
   * @return string
   *   A HTML string of rendered content.
   */
  private function getHtml(EntityInterface $entity): string {
    // Get the current active langcode from the site.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Get the rendered entity view in default mode.
    $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, 'default', $langcode);
    $rendered = $this->renderer->render($view);

    // Convert to string and strip HTML for sentiments analysis.
    $content = (string) $rendered;

    // Clean up the content for sentiments analysis.
    $content = strip_tags($content);
    $content = str_replace('&nbsp;', ' ', $content);
    // Replace multiple whitespace characters (spaces, tabs, newlines)
    // with a single space.
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);

    return $content;
  }

  /**
   * Analyze the sentiments of entity content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to analyze.
   *
   * @return array<string, float>
   *   Array with sentiments scores.
   */
  protected function analyzeSentiments(EntityInterface $entity): array {
    try {
      // Get the content to analyze.
      $content = $this->getHtml($entity);

      // Get the AI provider.
      $ai_provider = $this->getAiProvider();
      if (!$ai_provider) {
        return [];
      }

      // Get the default model.
      $defaults = $this->getDefaultModel();
      if (!$defaults) {
        return [];
      }

      // Build the prompt.
      $enabled_sentiments = $this->getEnabledSentiments($entity->getEntityTypeId(), $entity->bundle());

      // Build sentiments descriptions with their ranges.
      $sentiments_descriptions = [];
      foreach ($enabled_sentiments as $id => $sentiment) {
        $sentiments_descriptions[] = sprintf(
              "- %s: Score from -1.0 (%s) to +1.0 (%s), with 0.0 being %s",
              $sentiment['label'],
              $sentiment['min_label'],
              $sentiment['max_label'],
              $sentiment['mid_label']
          );
      }

      // Build dynamic JSON structure based on enabled sentiments.
      $json_keys = array_map(function ($id, $sentiments) {
          return '"' . $id . '": number';
      }, array_keys($enabled_sentiments), $enabled_sentiments);

      $json_template = '{' . implode(', ', $json_keys) . '}';

      $metrics = implode("\n", $sentiments_descriptions);

      $prompt = <<<EOT
<task>Analyze the following text.</task>
<text>
$content
</text>

<metrics>
$metrics
</metrics>

<instructions>Provide precise scores between -1.0 and +1.0 using any decimal values that best represent the sentiments.</instructions>
<output_format>Respond with a simple JSON object containing only the required scores:
$json_template</output_format>
EOT;

      $chat_array = [
        new ChatMessage('user', $prompt),
      ];

      // Get response.
      $messages = new ChatInput($chat_array);
      $message = $ai_provider->chat($messages, $defaults['model_id'])->getNormalized();

      // Use the injected PromptJsonDecoder service.
      $decoded = $this->promptJsonDecoder->decode($message);

      // If we couldn't decode the JSON at all.
      if (!is_array($decoded)) {
        return [];
      }

      // Validate and normalize scores to ensure they're within -1 to +1 range.
      $scores = [];
      foreach ($enabled_sentiments as $id => $sentiment) {
        if (isset($decoded[$id])) {
          $score = (float) $decoded[$id];
          // Clamp score to -1 to +1 range.
          $scores[$id] = max(-1.0, min(1.0, $score));
        }
      }

      return $scores;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(string $entity_type_id, ?string $bundle = NULL): array {
    return $this->getEntityTypeSettings($entity_type_id, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function saveSettings(string $entity_type_id, ?string $bundle, array $settings): void {
    /** @var array<string, mixed> $settings */
    $config = $this->configFactory->getEditable('analyze.settings');
    $current = $config->get('status') ?? [];

    // Save enabled state.
    if (isset($settings['enabled'])) {
      $current[$entity_type_id][$bundle][$this->getPluginId()] = $settings['enabled'];
      $config->set('status', $current)->save();
    }

    // Save sentiments settings if present.
    if (isset($settings['sentiments'])) {
      $detailed_config = $this->configFactory->getEditable('analyze.plugin_settings');
      $key = sprintf('%s.%s.%s', $entity_type_id, $bundle, $this->getPluginId());
      $detailed_config->set($key, ['sentiments' => $settings['sentiments']])->save();
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Default settings array.
   */
  public function getDefaultSettings(): array {
    $sentiments = $this->getConfiguredSentiments();
    $default_sentiments = [];

    foreach ($sentiments as $id => $sentiment) {
      $default_sentiments[$id] = TRUE;
    }

    return [
      'enabled' => TRUE,
      'settings' => [
        'sentiments' => $default_sentiments,
      ],
    ];
  }

  /**
   * Gets the configurable settings for this analyzer.
   *
   * Defines the form elements for configuring sentiments metrics.
   *
   * @return array<string, mixed>
   *   An array of configurable settings.
   */
  public function getConfigurableSettings(): array {
    $sentiments = $this->getConfiguredSentiments();
    $settings = [];

    foreach ($sentiments as $id => $sentiment) {
      $settings[$id] = [
        'type' => 'checkbox',
        'title' => $sentiment['label'],
        'default_value' => TRUE,
      ];
    }

    return [
      'sentiments' => [
        'type' => 'fieldset',
        'title' => $this->t('Sentiments'),
        'description' => $this->t('Select which sentiments metrics to analyze.'),
        'settings' => $settings,
      ],
    ];
  }

  /**
   * Gets the AI provider instance configured for chat operations.
   *
   * @return mixed|null
   *   The configured AI provider, or NULL if none available.
   */
  private function getAiProvider() {
    // Check if we have any chat providers available.
    if (!$this->aiProvider->hasProvidersForOperationType('chat', TRUE)) {
      return NULL;
    }

    // Get the default provider for chat.
    $defaults = $this->getDefaultModel();
    if (empty($defaults['provider_id'])) {
      return NULL;
    }

    // Initialize AI provider.
    $ai_provider = $this->aiProvider->createInstance($defaults['provider_id']);

    // Configure provider with low temperature for more consistent results.
    if (method_exists($ai_provider, 'setConfiguration')) {
      $ai_provider->setConfiguration(['temperature' => 0.2]);
    }

    return $ai_provider;
  }

  /**
   * Gets the default model configuration for chat operations.
   *
   * @return array<string, string>|null
   *   Array containing provider_id and model_id, or NULL if not configured.
   */
  private function getDefaultModel(): ?array {
    $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
    if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
      return NULL;
    }
    return $defaults;
  }

}
