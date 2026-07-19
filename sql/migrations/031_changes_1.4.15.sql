-- Chess Coach v1.4.15
-- Recalculate stored analysis counters from the authenticated player's perspective
-- and retire the previous opening-review weekly goal semantics.

UPDATE game_analysis a
JOIN games g ON g.id=a.game_id AND g.user_id=a.user_id
JOIN users u ON u.id=a.user_id
SET
  a.blunders=(
    SELECT COUNT(*)
    FROM game_move_analysis m
    WHERE m.analysis_id=a.id
      AND m.classification='blunder'
      AND (
        (LOWER(TRIM(g.white_player))=LOWER(TRIM(u.username)) AND MOD(m.ply,2)=1)
        OR
        (LOWER(TRIM(g.black_player))=LOWER(TRIM(u.username)) AND MOD(m.ply,2)=0)
      )
  ),
  a.mistakes=(
    SELECT COUNT(*)
    FROM game_move_analysis m
    WHERE m.analysis_id=a.id
      AND m.classification='mistake'
      AND (
        (LOWER(TRIM(g.white_player))=LOWER(TRIM(u.username)) AND MOD(m.ply,2)=1)
        OR
        (LOWER(TRIM(g.black_player))=LOWER(TRIM(u.username)) AND MOD(m.ply,2)=0)
      )
  ),
  a.inaccuracies=(
    SELECT COUNT(*)
    FROM game_move_analysis m
    WHERE m.analysis_id=a.id
      AND m.classification='inaccuracy'
      AND (
        (LOWER(TRIM(g.white_player))=LOWER(TRIM(u.username)) AND MOD(m.ply,2)=1)
        OR
        (LOWER(TRIM(g.black_player))=LOWER(TRIM(u.username)) AND MOD(m.ply,2)=0)
      )
  )
WHERE a.status='done';

UPDATE training_plan_goals
SET status='dismissed',completed_at=NULL,updated_at=NOW()
WHERE goal_type='opening_review'
  AND period_end>=CURDATE()
  AND status IN ('pending','completed');

INSERT INTO app_migrations (version, description)
VALUES ('1.4.15', 'Player-perspective metrics and bounded opening exercise goals')
ON DUPLICATE KEY UPDATE description=VALUES(description);
