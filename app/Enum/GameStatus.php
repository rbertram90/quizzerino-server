<?php

namespace rbwebdesigns\quizzerino\Enum;

enum GameStatus: int
{
    case GAME_STATUS_AWAITING_START = 0;
    case GAME_STATUS_PLAYERS_CHOOSING = 1;
    case GAME_STATUS_ROUND_WON = 3;
    case GAME_STATUS_GAME_WON = 4;
}
