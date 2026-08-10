# Nova assets

`nova-status-sprite.png` contains six transparent 512 x 512 cells in a 3 x 2 grid.

Use the shared helpers from `includes/nova.php` instead of referencing sprite coordinates from page code.

| State | Position | Intended use |
| --- | --- | --- |
| `success` | top left | Correct result or completed objective |
| `neutral` | top center | General information |
| `warning` | top right | Attention or recoverable issue |
| `thinking` | bottom left | Analysis or preparation |
| `focus` | bottom center | Recommended work or current focus |
| `error` | bottom right | Incorrect result or blocking problem |

Nova should appear only when the coach is proposing, explaining or responding to the player's work. It should not decorate ordinary navigation, filters or administrative actions.
