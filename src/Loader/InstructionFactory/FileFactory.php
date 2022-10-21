<?php
/*
 * BSD 3-Clause License
 *
 * Copyright (c) 2022, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace TASoft\InstructionQueue\Loader\InstructionFactory;


use TASoft\InstructionQueue\Exception\FileNotFoundException;
use TASoft\InstructionQueue\Instruction\CountedCallbackInstruction;
use TASoft\InstructionQueue\Instruction\InstructionInterface;
use TASoft\InstructionQueue\Instruction\SingleCallbackInstruction;
use TASoft\InstructionQueue\Loader\Model\InstructionData;

class FileFactory extends CallbackFactory implements InstructionPreflightFactoryInterface
{
    const CONFIG_ARRAY_ONLY_KEY = 'array_only';
    const CONFIG_PARAMETER_LIST_KEY = 'params';
    const CONFIG_PREFLIGHT_KEY = 'p_call';
    const CONFIG_USE_FN_AS_FACTORY = 'use_fn';

    private $preflight_callback;

    public function __construct(string $filename)
    {
        list($name) = explode(".", basename($filename));
        if(!is_file($filename))
            throw (new FileNotFoundException("Input file does not exist"))->setFilename($filename);

        $CONFIG = [
            self::CONFIG_USE_FN_AS_FACTORY => true
        ];

        $fn = require $filename;

        if(!is_callable($fn)) {
            throw new \InvalidArgumentException("File must return a callable");
        }

        $this->preflight_callback = $CONFIG[ static::CONFIG_PREFLIGHT_KEY ] ?? NULL;

        $callback = function($data) use ($fn, &$CONFIG) {
            if($CONFIG[ static::CONFIG_ARRAY_ONLY_KEY ] ?? true)
                $data = (array) $data;
            elseif($CONFIG[static::CONFIG_PARAMETER_LIST_KEY] ?? NULL) {
                $data = (array) $data;
                $instruction = $fn(...array_values($data));
            }

            if(!isset($instruction))
                $instruction = $fn($data);

            if(is_array($instruction)) {
                list($i, $tg) = array_values($instruction);
                if(is_numeric($i) && is_callable($tg)) {
                    list($tg, $i) = array_values($instruction);
                }

                if(is_callable($i) && is_int($tg))
                    return new CountedCallbackInstruction($i, $tg);
            } elseif (is_callable($instruction))
                return new SingleCallbackInstruction($instruction);
            elseif($instruction instanceof InstructionInterface)
                return $instruction;
            elseif(($CONFIG[self::CONFIG_USE_FN_AS_FACTORY] ?? true) && is_callable($fn)) {
                return new SingleCallbackInstruction($fn);
            }

            return NULL;
        };

        parent::__construct($name, $callback);
    }

    /**
     * @inheritDoc
     */
    public function getProcessCycleCount(InstructionData $data): int
    {
        if(is_callable($this->preflight_callback)) {
            return ($this->preflight_callback)($data);
        }
        return 1;
    }
}