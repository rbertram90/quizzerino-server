<?php

namespace rbwebdesigns\quizzerino;

interface SourceInterface {

    // public function getQuestions($numberToGet);
    public function getQuestion() : array;

}