<?php

namespace rbwebdesigns\quizzerino;

interface SourceInterface {

    /**
     * Quiz source constructor
     * 
     * @param array|FALSE $definition  An object of settings data (from the json definition)
     * @param array $settings  Array of values for settings chosen in the UI.
     */
    public function __construct(\stdClass|FALSE $definition, array $settings);

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