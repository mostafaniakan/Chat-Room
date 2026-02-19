<?php

return [
    'message_ttl_minutes' => max(1, (int) env('MESSAGE_TTL_MINUTES', 10)),
    'voice_wipe_passes' => max(1, (int) env('VOICE_WIPE_PASSES', 1)),
];
