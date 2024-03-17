<?php

namespace rbwebdesigns\quizzerino\Enum;

enum PlayerStatus: string
{
    case STATUS_IN_PLAY = 'Thinking...';
    case STATUS_ANSWER_CHOSEN = 'Answer submitted';
    case STATUS_CONNECTED = 'Connected';
    case STATUS_DISCONNECTED = 'Disconnected';
}
