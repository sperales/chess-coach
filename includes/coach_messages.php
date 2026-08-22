<?php
require_once __DIR__ . '/db.php';

const COACH_MESSAGE_VERSION = 1;

function coach_message_types(): array {
  return ['intro', 'selection', 'feedback', 'hint', 'explanation', 'completion', 'system'];
}

function coach_message_states(): array {
  return ['idle', 'thinking', 'correct', 'error', 'hint', 'explaining', 'session_complete'];
}

function coach_message_semantic_state(string $state): string {
  $legacy = [
    'welcome' => 'idle',
    'neutral' => 'idle',
    'success' => 'correct',
    'warning' => 'hint',
    'focus' => 'explaining',
  ];
  $state = $legacy[$state] ?? $state;
  return in_array($state, coach_message_states(), true) ? $state : 'idle';
}

function coach_message_payload(string $type, string $state, string $text, array $metadata = [], ?int $relatedPly = null): array {
  $type = in_array($type, coach_message_types(), true) ? $type : 'system';
  $state = coach_message_semantic_state($state);
  return [
    'type' => $type,
    'state' => $state,
    'text' => trim($text),
    'related_ply' => $relatedPly && $relatedPly > 0 ? $relatedPly : null,
    'metadata' => $metadata,
  ];
}

function coach_decode_json(?string $json): array {
  if (!is_string($json) || trim($json) === '') return [];
  $decoded = json_decode($json, true);
  return is_array($decoded) ? $decoded : [];
}

function coach_public_message(array $row): array {
  return [
    'id' => (int)$row['id'],
    'sequence' => (int)$row['sequence_no'],
    'type' => (string)$row['message_type'],
    'state' => coach_message_semantic_state((string)$row['coach_state']),
    'text' => (string)$row['message_text'],
    'related_ply' => $row['related_ply'] === null ? null : (int)$row['related_ply'],
    'metadata' => coach_decode_json($row['metadata_json'] ?? null),
    'created_at' => $row['created_at'],
  ];
}

function coach_messages_for_run(int $userId, int $runId): array {
  if ($userId <= 0 || $runId <= 0) return [];
  $st = db()->prepare('SELECT * FROM training_coach_messages WHERE user_id=? AND solve_run_id=? ORDER BY sequence_no,id');
  $st->execute([$userId, $runId]);
  return array_map('coach_public_message', $st->fetchAll());
}

function coach_record_message(
  int $userId,
  int $sessionId,
  array $message,
  ?int $sessionItemId = null,
  ?int $solveRunId = null
): array {
  if ($userId <= 0 || $sessionId <= 0) throw new InvalidArgumentException('Entrenamiento no válido para el Coach Feed.');
  $payload = coach_message_payload(
    (string)($message['type'] ?? 'system'),
    (string)($message['state'] ?? 'neutral'),
    (string)($message['text'] ?? ''),
    is_array($message['metadata'] ?? null) ? $message['metadata'] : [],
    isset($message['related_ply']) ? (int)$message['related_ply'] : null
  );
  if ($payload['text'] === '') throw new InvalidArgumentException('El mensaje del Coach no puede estar vacío.');

  $pdo = db();
  $pdo->beginTransaction();
  try {
    $sessionSt = $pdo->prepare('SELECT id FROM training_sessions WHERE id=? AND user_id=? LIMIT 1 FOR UPDATE');
    $sessionSt->execute([$sessionId, $userId]);
    if (!$sessionSt->fetchColumn()) throw new RuntimeException('Entrenamiento no encontrado.');
    $sequenceSt = $pdo->prepare('SELECT COALESCE(MAX(sequence_no),0)+1 FROM training_coach_messages WHERE session_id=?');
    $sequenceSt->execute([$sessionId]);
    $sequence = (int)$sequenceSt->fetchColumn();
    $metadataJson = $payload['metadata']
      ? json_encode($payload['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
      : null;
    $insert = $pdo->prepare('INSERT INTO training_coach_messages
      (user_id,session_id,session_item_id,solve_run_id,sequence_no,message_type,coach_state,message_text,related_ply,metadata_json,created_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
    $insert->execute([
      $userId, $sessionId, $sessionItemId, $solveRunId, $sequence, $payload['type'], $payload['state'],
      $payload['text'], $payload['related_ply'], $metadataJson,
    ]);
    $id = (int)$pdo->lastInsertId();
    $st = $pdo->prepare('SELECT * FROM training_coach_messages WHERE id=? AND user_id=?');
    $st->execute([$id, $userId]);
    $row = $st->fetch();
    $pdo->commit();
    if (!$row) throw new RuntimeException('No se pudo recuperar el mensaje del Coach.');
    return coach_public_message($row);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

function coach_record_run_message(int $userId, int $runId, array $message): ?array {
  $st = db()->prepare('SELECT session_id,session_item_id FROM training_solve_runs WHERE id=? AND user_id=? LIMIT 1');
  $st->execute([$runId, $userId]);
  $run = $st->fetch();
  if (!$run || empty($run['session_id'])) return null;
  return coach_record_message(
    $userId,
    (int)$run['session_id'],
    $message,
    empty($run['session_item_id']) ? null : (int)$run['session_item_id'],
    $runId
  );
}
