<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2020 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace App\Controller\Component;

use App\Core\I18n\TranslatorInterface;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Utility\Hash;

/**
 * Translator component. Provide utilities to translate texts.
 */
class TranslatorComponent extends Component
{
    /**
     * Text to translate
     *
     * @var string
     */
    protected $text = null;

    /**
     * Source language
     *
     * @var string
     */
    protected $source = null;

    /**
     * Target language
     *
     * @var string
     */
    protected $target = null;

    /**
     * Translator engine
     *
     * @var TranslatorInterface
     */
    protected $Translator = null;

    /**
     * {@inheritDoc}
     */
    public function initialize(array $config)
    {
        $translator = (array)Configure::read('Translator');
        if (!empty($translator) && !empty($translator['class']) && !empty($translator['options'])) {
            extract($translator);
            $this->Translator = new $class();
            $this->Translator->setup($options);
        }
        parent::initialize($config);
    }

    /**
     * Translate a text $text from language source $from to language target $to
     *
     * @param string $text The text to translate
     * @param string $from The source language
     * @param string $to The target language
     * @return string The translation
     * @throws \Exception when no translator engine is set in configuration
     */
    public function translate(string $text, string $from, string $to): string
    {
        if ($this->Translator === null) {
            throw new \Exception('No translator engine is set in configuration');
        }

        return $this->Translator->translate($text, $from, $to);
    }
}
