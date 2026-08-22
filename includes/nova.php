<?php

function nova_semantic_state(string $state): string {
  $aliases = [
    'success' => 'correct',
    'neutral' => 'idle',
    'warning' => 'hint',
    'focus' => 'explaining',
    'welcome' => 'idle',
  ];
  $state = $aliases[$state] ?? $state;
  $allowed = ['idle', 'thinking', 'correct', 'error', 'hint', 'explaining', 'session_complete'];
  return in_array($state, $allowed, true) ? $state : 'idle';
}

function nova_state(string $state): string {
  return match (nova_semantic_state($state)) {
    'correct', 'session_complete' => 'success',
    'hint' => 'warning',
    'explaining' => 'focus',
    'thinking' => 'thinking',
    'error' => 'error',
    default => 'neutral',
  };
}

function nova_avatar_html(string $state = 'neutral', string $label = 'Nova'): string {
  $semantic = nova_semantic_state($state);
  $visual = nova_state($semantic);
  return '<span class="nova-avatar nova-avatar--'.e($visual).'" data-nova-state="'.e($semantic).'" role="img" aria-label="'.e($label).'"></span>';
}

function nova_internal_href(string $href): string {
  if ($href === '' || str_starts_with($href, '//') || preg_match('/[\x00-\x1F\x7F]/', $href)) return '';
  $parts = parse_url($href);
  if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return '';
  return $href;
}

function nova_coach_html(array $options = []): string {
  $semantic = nova_semantic_state((string)($options['state'] ?? 'idle'));
  $state = nova_state($semantic);
  $title = trim((string)($options['title'] ?? 'Nova'));
  $message = trim((string)($options['message'] ?? ''));
  $actionLabel = trim((string)($options['action_label'] ?? ''));
  $actionHref = nova_internal_href(trim((string)($options['action_href'] ?? '')));
  $compact = !empty($options['compact']);

  $html = '<section class="nova-coach nova-coach--'.e($state).($compact ? ' nova-coach--compact' : '').'" data-nova-state="'.e($semantic).'">';
  $html .= nova_avatar_html($semantic);
  $html .= '<div class="nova-coach__content">';
  if ($title !== '') $html .= '<strong class="nova-coach__title">'.e($title).'</strong>';
  if ($message !== '') $html .= '<p class="nova-coach__message">'.e($message).'</p>';
  if ($actionLabel !== '' && $actionHref !== '') {
    $html .= '<a class="nova-coach__action" href="'.e($actionHref).'">'.e($actionLabel).'</a>';
  }
  $html .= '</div></section>';

  return $html;
}
