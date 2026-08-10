<?php

function nova_state(string $state): string {
  $allowed = ['success', 'neutral', 'warning', 'thinking', 'focus', 'error'];
  return in_array($state, $allowed, true) ? $state : 'neutral';
}

function nova_avatar_html(string $state = 'neutral', string $label = 'Nova'): string {
  $state = nova_state($state);
  return '<span class="nova-avatar nova-avatar--'.e($state).'" role="img" aria-label="'.e($label).'"></span>';
}

function nova_internal_href(string $href): string {
  if ($href === '' || str_starts_with($href, '//') || preg_match('/[\x00-\x1F\x7F]/', $href)) return '';
  $parts = parse_url($href);
  if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return '';
  return $href;
}

function nova_coach_html(array $options = []): string {
  $state = nova_state((string)($options['state'] ?? 'neutral'));
  $title = trim((string)($options['title'] ?? 'Nova'));
  $message = trim((string)($options['message'] ?? ''));
  $actionLabel = trim((string)($options['action_label'] ?? ''));
  $actionHref = nova_internal_href(trim((string)($options['action_href'] ?? '')));
  $compact = !empty($options['compact']);

  $html = '<section class="nova-coach nova-coach--'.e($state).($compact ? ' nova-coach--compact' : '').'">';
  $html .= nova_avatar_html($state);
  $html .= '<div class="nova-coach__content">';
  if ($title !== '') $html .= '<strong class="nova-coach__title">'.e($title).'</strong>';
  if ($message !== '') $html .= '<p class="nova-coach__message">'.e($message).'</p>';
  if ($actionLabel !== '' && $actionHref !== '') {
    $html .= '<a class="nova-coach__action" href="'.e($actionHref).'">'.e($actionLabel).'</a>';
  }
  $html .= '</div></section>';

  return $html;
}
