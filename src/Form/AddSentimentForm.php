<?php

namespace Drupal\analyze_ai_sentiment\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding a new sentiment.
 */
class AddSentimentForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new AddSentimentForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analyze_ai_sentiment_add_sentiment';
  }

  /**
   * Check if a sentiment ID already exists.
   *
   * @param string $id
   *   The sentiment ID to check.
   *
   * @return bool
   *   TRUE if the sentiment exists, FALSE otherwise.
   */
  public function sentimentExists($id) {
    $config = $this->configFactory->get('analyze_ai_sentiment.settings');
    $sentiments = $config->get('sentiments') ?: [];
    return isset($sentiments[$id]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Add a new sentiment metric to analyze content. Each sentiment has a scale from -1.0 to +1.0 with customizable labels for the minimum, middle, and maximum values.'),
    ];

    $form['basic'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['sentiment-basic-info']],
    ];

    $form['basic']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#required' => TRUE,
      '#description' => $this->t('The human-readable name for this sentiment metric.'),
      '#placeholder' => $this->t('e.g., Content Sentiment'),
      '#maxlength' => 255,
    ];

    $form['basic']['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#required' => TRUE,
      '#description' => $this->t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#machine_name' => [
        'exists' => [$this, 'sentimentExists'],
        'source' => ['basic', 'label'],
      ],
    ];

    $form['labels'] = [
      '#type' => 'details',
      '#title' => $this->t('Scale Labels'),
      '#description' => $this->t('Define labels for the sentiment scale endpoints and midpoint.'),
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
      '#attributes' => ['class' => ['sentiment-form-actions']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Sentiment'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('analyze_ai_sentiment.settings'),
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('analyze_ai_sentiment.settings');
    $sentiments = $config->get('sentiments') ?: [];
    
    // Get the maximum weight and add 1
    $max_weight = 0;
    foreach ($sentiments as $sentiment) {
      $max_weight = max($max_weight, $sentiment['weight'] ?? 0);
    }
    
    $values = $form_state->getValues();
    $sentiments[$values['id']] = [
      'id' => $values['id'],
      'label' => $values['label'],
      'min_label' => $values['min_label'],
      'mid_label' => $values['mid_label'],
      'max_label' => $values['max_label'],
      'weight' => $max_weight + 1,
    ];
    
    $config->set('sentiments', $sentiments)->save();
    $this->messenger()->addStatus($this->t('Added new sentiment %label.', ['%label' => $values['label']]));
    $form_state->setRedirectUrl(\Drupal\Core\Url::fromRoute('analyze_ai_sentiment.settings'));
  }

} 