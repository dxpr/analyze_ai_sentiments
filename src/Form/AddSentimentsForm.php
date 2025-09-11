<?php

namespace Drupal\analyze_ai_sentiments\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\analyze_ai_sentiments\Service\SentimentsStorageService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding a new sentiments.
 */
class AddSentimentsForm extends FormBase {
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The sentiments storage service.
   *
   * @var \Drupal\analyze_ai_sentiments\Service\SentimentsStorageService
   */
  protected $sentimentsStorage;

  /**
   * Constructs a new AddSentimentsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\analyze_ai_sentiments\Service\SentimentsStorageService $sentiments_storage
   *   The sentiments storage service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    SentimentsStorageService $sentiments_storage,
  ) {
    $this->configFactory = $config_factory;
    $this->sentimentsStorage = $sentiments_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @var static */
    return new self(
          $container->get('config.factory'),
          $container->get('analyze_ai_sentiments.storage')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analyze_ai_sentiments_add_sentiments';
  }

  /**
   * Check if a sentiments ID already exists.
   *
   * @param string $id
   *   The sentiments ID to check.
   *
   * @return bool
   *   TRUE if the sentiments exists, FALSE otherwise.
   */
  public function sentimentsExists($id) {
    return $this->sentimentsStorage->sentimentExists($id);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var array<string, mixed> $form */
    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Add a new sentiments metric to analyze content. Each sentiments has a scale from -1.0 to +1.0 with customizable labels for the minimum, middle, and maximum values.'),
    ];

    $form['basic'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['sentiments-basic-info']],
    ];

    $form['basic']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#required' => TRUE,
      '#description' => $this->t('The human-readable name for this sentiments metric.'),
      '#placeholder' => $this->t('e.g., Content Sentiments'),
      '#maxlength' => 255,
    ];

    $form['basic']['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#required' => TRUE,
      '#description' => $this->t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#machine_name' => [
        'exists' => [$this, 'sentimentsExists'],
        'source' => ['basic', 'label'],
      ],
    ];

    $form['labels'] = [
      '#type' => 'details',
      '#title' => $this->t('Scale Labels'),
      '#description' => $this->t('Define labels for the sentiments scale endpoints and midpoint.'),
      '#open' => TRUE,
    ];

    $form['labels']['min_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minimum Label (-1.0)'),
      '#required' => TRUE,
      '#description' => $this->t('Label for the most negative value on the scale.'),
      '#placeholder' => $this->t('e.g., Negative'),
    ];

    $form['labels']['mid_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Middle Label (0.0)'),
      '#required' => TRUE,
      '#description' => $this->t('Label for the neutral midpoint of the scale.'),
      '#placeholder' => $this->t('e.g., Neutral'),
    ];

    $form['labels']['max_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum Label (+1.0)'),
      '#required' => TRUE,
      '#description' => $this->t('Label for the most positive value on the scale.'),
      '#placeholder' => $this->t('e.g., Positive'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['sentiments-form-actions']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Sentiments'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('analyze_ai_sentiments.settings'),
      '#attributes' => [
        'class' => ['button', 'dialog-cancel'],
        'role' => 'button',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var array<string, mixed> $form */
    $values = $form_state->getValues();

    // Get the maximum weight and add 1.
    $sentiments = $this->sentimentsStorage->getAllSentiments();
    $max_weight = 0;
    foreach ($sentiments as $sentiment) {
      $max_weight = max($max_weight, $sentiment['weight'] ?? 0);
    }

    $this->sentimentsStorage->saveSentiment(
      $values['id'],
      $values['label'],
      $values['min_label'],
      $values['mid_label'],
      $values['max_label'],
      $max_weight + 1
    );

    $this->messenger()->addStatus($this->t('Added new sentiment %label.', ['%label' => $values['label']]));
    $form_state->setRedirectUrl(Url::fromRoute('analyze_ai_sentiments.settings'));
  }

}
