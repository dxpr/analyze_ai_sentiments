<?php

namespace Drupal\analyze_ai_sentiment\Plugin\Analyze;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
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

/**
 * A sentiment analyzer that uses AI to analyze content sentiment.
 *
 * @Analyze(
 *   id = "ai_sentiment_analyzer",
 *   label = @Translation("Sentiment Analysis"),
 *   description = @Translation("Analyzes the sentiment of content using AI.")
 * )
 */
final class AiSentimentAnalyzer extends AnalyzePluginBase {

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
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $helper, $currentUser);
    $this->aiProvider = $aiProvider;
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->promptJsonDecoder = $promptJsonDecoder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
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
    );
  }

  /**
   * Get configured sentiments.
   *
   * @return array
   *   Array of sentiment configurations.
   */
  protected function getConfiguredSentiments(): array {
    $config = $this->configFactory->get('analyze_ai_sentiment.settings');
    $sentiments = $config->get('sentiments');
    
    if (empty($sentiments)) {
      // Load defaults from the settings form
      $form = \Drupal::classResolver()
        ->getInstanceFromDefinition('\Drupal\analyze_ai_sentiment\Form\SentimentSettingsForm');
      return $form->getDefaultSentiments();
    }
    
    return $sentiments;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeSettingsForm(string $entity_type_id, ?string $bundle = NULL): array {
    // Only use parent implementation which will use getConfigurableSettings()
    return parent::getEntityTypeSettingsForm($entity_type_id, $bundle);
  }

  /**
   * Gets the enabled sentiments for an entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle ID.
   *
   * @return array
   *   Array of enabled sentiment IDs.
   */
  protected function getEnabledSentiments(string $entity_type_id, ?string $bundle = NULL): array {
    if (!$this->isEnabledForEntityType($entity_type_id, $bundle)) {
      return [];
    }

    // Get settings from plugin_settings config
    $plugin_settings_config = $this->getConfigFactory()->get('analyze.plugin_settings');
    $key = sprintf('%s.%s.%s', $entity_type_id, $bundle, $this->getPluginId());
    $settings = $plugin_settings_config->get($key) ?? [];
    
    // Get all available sentiments
    $sentiments = $this->getConfiguredSentiments();
    
    $enabled = [];
    foreach ($sentiments as $id => $sentiment) {
      // If no settings exist yet, enable all sentiments by default
      if (!isset($settings['sentiments'])) {
        $enabled[$id] = $sentiment;
      }
      // Otherwise check if explicitly enabled in settings
      elseif (isset($settings['sentiments'][$id]) && $settings['sentiments'][$id]) {
        $enabled[$id] = $sentiment;
      }
    }

    // Sort enabled sentiments by weight
    uasort($enabled, function ($a, $b) {
      return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    return $enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function renderSummary(EntityInterface $entity): array {
    // First check if the plugin itself is enabled for this entity
    $status_config = $this->getConfigFactory()->get('analyze.settings');
    $status = $status_config->get('status') ?? [];
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    
    if (!isset($status[$entity_type][$bundle][$this->getPluginId()])) {
      return [
        '#theme' => 'analyze_table',
        '#table_title' => 'Sentiment Analysis',
        '#rows' => [
          [
            'label' => 'Status',
            'data' => 'Sentiment analysis is not enabled for this content type.',
          ],
        ],
      ];
    }

    $enabled_sentiments = $this->getEnabledSentiments($entity->getEntityTypeId(), $entity->bundle());
    if (empty($enabled_sentiments)) {
      return [
        '#theme' => 'analyze_table',
        '#table_title' => 'Sentiment Analysis',
        '#rows' => [
          [
            'label' => 'Status',
            'data' => 'No sentiment metrics are currently enabled.',
          ],
        ],
      ];
    }

    $scores = $this->analyzeSentiment($entity);
    
    // For summary, we'll just show the first enabled sentiment gauge if available
    $sentiment = reset($enabled_sentiments);
    $id = key($enabled_sentiments);
    
    if (isset($scores[$id])) {
      // Convert -1 to +1 range to 0 to 1 for gauge
      $gauge_value = ($scores[$id] + 1) / 2;
      
      return [
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
    
    // If no scores available but everything is configured correctly, show a helpful message
    if (!empty($content = $this->getHtml($entity))) {
      return [
        '#theme' => 'analyze_table',
        '#table_title' => 'Sentiment Analysis',
        '#rows' => [
          [
            'label' => 'Status',
            'data' => $this->t('No chat AI provider is configured for sentiment analysis. Please configure one in the %ai_settings_link.', [
              '%ai_settings_link' => Link::createFromRoute($this->t('AI settings'), 'ai.settings_form')
                  ->toString(),
            ]),
          ],
        ],
        '#attached' => [
          'library' => ['core/drupal.dialog.ajax'],
        ],
      ];
    }
    
    return [
      '#theme' => 'analyze_table',
      '#table_title' => 'Sentiment Analysis',
      '#rows' => [
        [
          'label' => 'Status',
          'data' => 'No content available for analysis.',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function renderFullReport(EntityInterface $entity): array {
    // First check if the plugin itself is enabled for this entity
    $status_config = $this->getConfigFactory()->get('analyze.settings');
    $status = $status_config->get('status') ?? [];
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    
    if (!isset($status[$entity_type][$bundle][$this->getPluginId()])) {
      return [];
    }

    $enabled_sentiments = $this->getEnabledSentiments($entity->getEntityTypeId(), $entity->bundle());
    if (empty($enabled_sentiments)) {
      return [];
    }

    $scores = $this->analyzeSentiment($entity);
    
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['analyze-sentiment-report'],
      ],
    ];

    $build['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Sentiment Analysis'),
    ];

    foreach ($enabled_sentiments as $id => $sentiment) {
      if (isset($scores[$id])) {
        // Convert -1 to +1 range to 0 to 1 for gauge
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
    }
    
    if (count($build) <= 1) {
      return [];
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
    // Get the current active langcode from the site
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Get the rendered entity view in default mode
    $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, 'default', $langcode);
    $rendered = $this->renderer->render($view);

    // Convert to string and strip HTML for sentiment analysis
    $content = is_object($rendered) && method_exists($rendered, '__toString')
      ? $rendered->__toString()
      : (string) $rendered;
      
    // Clean up the content for sentiment analysis
    $content = strip_tags($content);
    $content = str_replace('&nbsp;', ' ', $content);
    // Replace multiple whitespace characters (spaces, tabs, newlines) with a single space
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);
    
    return $content;
  }

  /**
   * Analyze the sentiment of entity content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to analyze.
   *
   * @return array
   *   Array with sentiment scores.
   */
  protected function analyzeSentiment(EntityInterface $entity): array {
    try {
      // Get the content to analyze
      $content = $this->getHtml($entity);

      // Get the AI provider
      $ai_provider = $this->getAiProvider();
      if (!$ai_provider) {
        $this->messenger->addError($this->t('No chat AI provider is configured for sentiment analysis. Please configure one in the %ai_settings_link.', [
          '%ai_settings_link' => Link::createFromRoute($this->t('AI settings'), 'ai.settings_form')
              ->toString(),
        ]));
        return [];
      }

      // Get the default model
      $defaults = $this->getDefaultModel();
      if (!$defaults) {
        return [];
      }

      // Build the prompt
      $enabled_sentiments = $this->getEnabledSentiments($entity->getEntityTypeId(), $entity->bundle());
      
      // Build sentiment descriptions with their ranges
      $sentiment_descriptions = [];
      foreach ($enabled_sentiments as $id => $sentiment) {
        $sentiment_descriptions[] = sprintf(
          "- %s: Score from -1.0 (%s) to +1.0 (%s), with 0.0 being %s",
          $sentiment['label'],
          $sentiment['min_label'],
          $sentiment['max_label'],
          $sentiment['mid_label']
        );
      }
      
      // Build dynamic JSON structure based on enabled sentiments
      $json_keys = array_map(function($id, $sentiment) {
        return '"' . $id . '": number';
      }, array_keys($enabled_sentiments), $enabled_sentiments);
      
      $json_template = '{' . implode(', ', $json_keys) . '}';

      $metrics = implode("\n", $sentiment_descriptions);
      
      $prompt = <<<EOT
<task>Analyze the following text.</task>
<text>
$content
</text>

<metrics>
$metrics
</metrics>

<instructions>Provide precise scores between -1.0 and +1.0 using any decimal values that best represent the sentiment.</instructions>
<output_format>Respond with a simple JSON object containing only the required scores:
$json_template</output_format>
EOT;
      
      $chat_array = [
        new ChatMessage('user', $prompt),
      ];

      // Get response
      $messages = new ChatInput($chat_array);
      $message = $ai_provider->chat($messages, $defaults['model_id'])->getNormalized();
      $raw_response = $message->getText();
      
      // Use the injected PromptJsonDecoder service
      $decoded = $this->promptJsonDecoder->decode($message);
      
      // If we couldn't decode the JSON at all
      if (!is_array($decoded)) {
        return [];
      }
      
      // Validate and normalize scores to ensure they're within -1 to +1 range
      $scores = [];
      foreach ($enabled_sentiments as $id => $sentiment) {
        if (isset($decoded[$id])) {
          $score = (float) $decoded[$id];
          // Clamp score to -1 to +1 range
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
  public function isApplicable(string $entity_type, ?string $bundle = NULL): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity): bool {
    return $this->currentUser->hasPermission('access content');
  }

  /**
   * {@inheritdoc}
   */
  public function getFullReportUrl(EntityInterface $entity): ?Url {
    return parent::getFullReportUrl($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function extraSummaryLinks(EntityInterface $entity): array {
    return [];
  }

  /**
   * Gets the public settings for this analyzer.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle ID.
   *
   * @return array
   *   The settings array.
   */
  public function getSettings(string $entity_type_id, ?string $bundle = NULL): array {
    return $this->getEntityTypeSettings($entity_type_id, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function saveSettings(string $entity_type_id, ?string $bundle, array $settings): void {
    $config = \Drupal::configFactory()->getEditable('analyze.settings');
    $current = $config->get('status') ?? [];

    // Save enabled state
    if (isset($settings['enabled'])) {
      $current[$entity_type_id][$bundle][$this->getPluginId()] = $settings['enabled'];
      $config->set('status', $current)->save();
    }

    // Save sentiment settings if present
    if (isset($settings['sentiments'])) {
      $detailed_config = \Drupal::configFactory()->getEditable('analyze.plugin_settings');
      $key = sprintf('%s.%s.%s', $entity_type_id, $bundle, $this->getPluginId());
      $detailed_config->set($key, ['sentiments' => $settings['sentiments']])->save();
    }
  }

  /**
   * Gets the default settings structure.
   *
   * @return array
   *   The default settings structure.
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
        'title' => $this->t('Sentiment'),
        'description' => $this->t('Select which sentiment metrics to analyze.'),
        'settings' => $settings,
      ],
    ];
  }

  private function getAiProvider() {
    // Check if we have any chat providers available
    if (!$this->aiProvider->hasProvidersForOperationType('chat', TRUE)) {
      return NULL;
    }

    // Get the default provider for chat
    $defaults = $this->getDefaultModel();
    if (empty($defaults['provider_id'])) {
      return NULL;
    }

    // Initialize AI provider
    $ai_provider = $this->aiProvider->createInstance($defaults['provider_id']);
    
    // Configure provider with low temperature for more consistent results
    $ai_provider->setConfiguration(['temperature' => 0.2]);
    
    return $ai_provider;
  }

  private function getDefaultModel() {
    $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
    if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
      return NULL;
    }
    return $defaults;
  }

} 