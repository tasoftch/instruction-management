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

namespace TASoft\InstructionQueue;


use TASoft\InstructionQueue\Instruction\InstructionInterface;
use TASoft\InstructionQueue\Instruction\SetupInstruction;

class LoopInstructionQueue extends SimpleInstructionQueue implements InstructionRunLoopQueueInterface
{
    private $interval;
    private $interruptCallback;

    /**
     * LoopInstructionQueue constructor.
     * @param int|null $interval
     * @param callable|null $interruptCallback
     * @param array $instructions
     */
    public function __construct(int $interval = NULL, callable $interruptCallback = NULL, array $instructions = [])
    {
        parent::__construct($instructions);
        $this->interval = $interval;
        $this->interruptCallback = $interruptCallback;
    }

    public function addInstruction(InstructionInterface $instruction, string $name = NULL)
    {
        if($instruction instanceof SetupInstruction) {
            if($this->interval == NULL && isset($instruction[ SetupInstruction::SETUP_INTERVAL_KEY ]))
                $this->interval = $instruction[ SetupInstruction::SETUP_INTERVAL_KEY ];
            if($this->interruptCallback == NULL && isset($instruction[ SetupInstruction::SETUP_TERMINATION_CALLBACK_KEY ]))
                $this->interruptCallback = $instruction[ SetupInstruction::SETUP_TERMINATION_CALLBACK_KEY ];
            return $this;
        }
        return parent::addInstruction($instruction, $name);
    }

    public function getInterruptCallback(): ?callable
    {
        return $this->interruptCallback;
    }

    public function getCycleInterval(): int
    {
        return $this->interval;
    }

    public function run()
    {
        if($cb = $this->getInterruptCallback()) {
            if(function_exists("pcntl_signal")) {
                $h = function() use ($cb) {
                    $cb();
                    echo "Bye", PHP_EOL;
                    exit(0);
                };
                pcntl_signal(SIGINT, $h);
                pcntl_signal(SIGTERM, $h);
            } else
                trigger_error("Can not create interrupt callback", E_USER_WARNING);
        }

        while (!$this->processCompleted()) {
            $this->process();
            $i = $this->getCycleInterval();

            declare(ticks=1) {
                usleep($i);
            }
        }

        if(is_callable( $cb = $this->getInterruptCallback() ))
            $cb();
    }
}