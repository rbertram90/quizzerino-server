<?php

namespace rbwebdesigns\quizzerino;

interface SourceInterface {

    // Constructor passed an object of settings data
    public function __construct($data);

    /**
     * Method which returns data for a single question, format:
     * array [
     *    'text' => 'Question Text'
     *    'options' => [
     *        'A', 'B', 'C', 'D'
     *    ],
     *    'correct_option_index' => 0
     * ]
     */
    public function getQuestion() : array;

}