<?php

namespace Drupal\issue_tracker\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a block which displays most active issues for a project on drupal.org
 *
 * @Block(
 *   id = "issue_tracker_block",
 *   admin_label = @Translation("Issue Tracker Block"),
 * )
 */
class IssueTrackerBlock extends BlockBase {
  /**
   * {@inerhitDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project name'),
      '#description' => $this->t('Project machine name (can only include lowercase letters and underscores)'),
      '#default_value' => $config['project_name'] ?? '',
      '#pattern' => '([a-z_])+',
    ];

    $form['max_items'] = [
      '#type' => 'select',
      '#title' => $this->t('Max. items'),
      '#description' => $this->t('Maximum number of items that can be displayed'),
      '#options' => [5 => '5', 10 => '10', 15 => '15'],
      '#default_value' => $config['max_items'] ?? 5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $project_name = $form_state->getValue('project_name');

    if (!issue_tracker_find_project($project_name)) {
      $form_state->setErrorByName('project_name', t('Looks like this project does not exist on Drupal.org'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('project_name', $form_state->getValue('project_name'));
    $this->setConfigurationValue('max_items', $form_state->getValue('max_items'));
  }

  /**
   * {@inerhitDoc}
   */
  public function build() {
    $config = $this->getConfiguration();

    if ($cache = \Drupal::cache()->get('issue_tracker:' . $config['project_name'])) {
      $data = $cache->data;
    }
    else {
      $data = issue_tracker_reload_project($config['project_name'], $config['max_items']);
    }

    $rows = [];
    foreach ($data as $row) {
      $rows[] = [
        \Drupal\Core\Link::fromTextAndUrl($row->title, \Drupal\Core\Url::fromUri($row->url)),
        $row->comment_count,
      ];
    }
    // At least we can sort by comments what we have.
    $comments = array_column($rows, 1);
    array_multisort($comments, SORT_DESC, $rows);

    $block = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Comments'),
      ],
      '#rows' => $rows,
    ];

    return $block;
  }
}
