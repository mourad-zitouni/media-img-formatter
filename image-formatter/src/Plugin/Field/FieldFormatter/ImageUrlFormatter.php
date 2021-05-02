<?php
/**
* @file
* Contains \Drupal\image_formatter\Plugin\field\formatter\ImageUrlFormatter.
*/

namespace Drupal\image_formatter\Plugin\Field\FieldFormatter;


use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\AdminContext;

/**
 * Plugin implementation of the 'image_url' formatter.
 *
 * @FieldFormatter(
 *   id = "image_url",
 *   label = @Translation("Image url"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ImageUrlFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

	/**
	 * @var EntityTypeManagerInterface
	 */
	protected $entityTypeManager;
	/**
	 * @var StreamWrapperManagerInterface
	 */
	protected $streamWrapperManager;
  /**
   * @var AdminContext
   */
  protected $admin_context;

	/**
	 * ImageUrlFormatter constructor.
	 * @param $plugin_id
	 * @param $plugin_definition
	 * @param FieldDefinitionInterface $field_definition
	 * @param array $settings
	 * @param $label
	 * @param $view_mode
	 * @param array $third_party_settings
	 * @param EntityTypeManagerInterface $entity_type_manager
	 * @param StreamWrapperManagerInterface $stream_wrapper_manager
   * @param AdminContext $admin_context
	 */
	public function __construct(
		$plugin_id,
		$plugin_definition,
		FieldDefinitionInterface $field_definition,
		array $settings,
		$label,
		$view_mode,
		array $third_party_settings,
		EntityTypeManagerInterface $entity_type_manager,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    AdminContext $admin_context
  ) {

		$this->entityTypeManager = $entity_type_manager;
		$this->streamWrapperManager = $stream_wrapper_manager;
    $this->admin_context = $admin_context;

		parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
	}

	/**
	 * {@inheritdoc}
	 */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
		return new static(
			$plugin_id,
			$plugin_definition,
			$configuration['field_definition'],
			$configuration['settings'],
			$configuration['label'],
			$configuration['view_mode'],
			$configuration['third_party_settings'],
			$container->get('entity_type.manager'),
      $container->get('stream_wrapper_manager'),
      $container->get('router.admin_context')
		);
	}

  /**
   * {@inheritdoc}
	 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
	 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
	 */
	public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $image_style_setting = $this->getSetting('image_style');

    // Check if Image style is required.
		/** @var  ImageStyleInterface $image_style */
    $image_style = !empty($image_style_setting) ? $this->entityTypeManager->getStorage('image_style')->load($image_style_setting) : NULL;

    foreach ($items as $delta => $item) {

      // Get the media item.
      $media_id = $item->getValue()['target_id'];

      /** @var MediaInterface $media_item */
      $media_item = $this->entityTypeManager->getStorage('media')->load($media_id);

      // Get the image file.
      $file_id = $media_item->field_media_image->getValue()[0]['target_id'];

      /** @var FileInterface $file */
      $file = $this->entityTypeManager->getStorage('file')->load($file_id);

      // Get the URL.
      $uri = $file->getFileUri();
			$wrapper = $this->streamWrapperManager->getViaUri($uri);
      $url = $image_style ? $image_style->buildUrl($uri) : Url::fromUri($wrapper->getExternalUrl())->toString();

      // Output.
      if (!$this->admin_context->isAdminRoute()) {
        $elements[$delta] = [
          '#type' => 'inline_template',
          '#template' => '{{ url }}',
          '#context' => [
            'url' => parse_url($url, PHP_URL_PATH)
          ],
        ];
      }

    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'image_style' => '',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $image_styles = image_style_options(FALSE);
    $element['image_style'] = array(
      '#title' => t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles,
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    $image_styles = image_style_options(FALSE);

    // Unset possible 'No defined styles' option.
    unset($image_styles['']);

    // Styles could be lost because of enabled/disabled modules that
    // defines their styles in code.
    $image_style_setting = $this->getSetting('image_style');
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = t('Image style: @style', array('@style' => $image_styles[$image_style_setting]));
    }
    else {
      $summary[] = t('Original image');
    }
    return $summary;
  }
}
