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


use Countable;
use TASoft\InstructionQueue\Exception\DuplicateAddressInstructionException;
use TASoft\InstructionQueue\Exception\ImmutableInstructionQueueException;
use TASoft\InstructionQueue\Instruction\AddressInstruction;
use TASoft\InstructionQueue\Instruction\InstructionAwareInterface;
use TASoft\InstructionQueue\Instruction\InstructionInterface;
use TASoft\InstructionQueue\Instruction\InstructionQueueDidLoadNotificationInterface;
use TASoft\InstructionQueue\Instruction\JumpInstructionInterface;
use TASoft\InstructionQueue\Instruction\ParallelInstructionInterface;
use TASoft\InstructionQueue\Instruction\ResetInterface;
use TASoft\InstructionQueue\Instruction\SyncInstructionInterface;

abstract class AbstractInstructionQueue implements TriggeredInstructionQueueInterface, Countable
{
    /** @var InstructionInterface[] */
    protected $instructions = [];
    private $addressInstructions = [];
    /** @var InstructionInterface[] */
    private $parallelInstructionStack = [];

    private $current_index = 0;
    private $ready = false;

    /**
     * StaticInstructionQueue constructor.
     * @param InstructionInterface[] $instructions
     */
    public function __construct(array $instructions = [])
    {
        foreach($instructions as $instruction)
            $this->addInstruction($instruction);
    }

    /**
     * @param InstructionInterface $instruction
     * @return static
     */
    public function removeInstruction(InstructionInterface $instruction) {
        if($this->isReady())
            throw new ImmutableInstructionQueueException("Can not change instruction queue after calling ready method");

        if(($idx = array_search($instruction, $this->instructions)) !== false)
            unset($this->instructions[$idx]);

        if($instruction instanceof AddressInstruction)
            unset($this->addressInstructions[ $instruction->getAddress() ]);
        return $this;
    }

    /**
     * @param array $instructions
     * @return static
     */
    public function setInstructions(array $instructions) {
        if($this->isReady())
            throw new ImmutableInstructionQueueException("Can not change instruction queue after calling ready method");

        $this->instructions = [];
        foreach($instructions as $instruction)
            $this->addInstruction($instruction);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addInstruction(InstructionInterface $instruction)
    {
        if($this->isReady())
            throw new ImmutableInstructionQueueException("Can not change instruction queue after calling ready method");

        $this->instructions[] = $instruction;

        if($instruction instanceof AddressInstruction) {
            if(isset($this->addressInstructions[ $instruction->getAddress() ])) {
                throw (new DuplicateAddressInstructionException("Address %s is already in use", 0, NULL, $instruction->getAddress()))->setAddress($instruction->getAddress());
            }
            $this->addressInstructions[ $instruction->getAddress() ] = $instruction;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function reset()
    {
        $this->parallelInstructionStack = [];
        $this->current_index = 0;

        array_walk($this->instructions, function($i) {
            if($i instanceof ResetInterface)
                $i->reset();
        });
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getInstructions(): array
    {
        return $this->instructions;
    }

    /**
     * @inheritDoc
     */
    public function ready()
    {
        if(!$this->isReady()) {
            $this->ready = true;
            array_walk($this->instructions, function($i) {
                if($i instanceof InstructionAwareInterface) {
                    if(($idx = array_search($i, $this->instructions)) !== false)
                        $i->instructionWasAddedAtIndex($idx);
                }
                if($i instanceof InstructionQueueDidLoadNotificationInterface)
                    $i->instructionQueueDidLoad($this);
            });
        }
        return $this;
    }


    private function _processParallelStack() {
        /**
         * @var int $idx
         * @var ParallelInstructionInterface $instruction
         */
        foreach($this->parallelInstructionStack as $idx => &$instruction) {
            $instruction->process($idx);
            if($instruction->isInstructionCompleted())
                $instruction = NULL;
        }

        $this->parallelInstructionStack = array_filter($this->parallelInstructionStack);
    }

    /**
     * Processes the current pending instructions now.
     */
    public function process()
    {
        if(!$this->isReady())
            $this->ready();

        if($this->current_index == -2) {
            return;
        }

        $this->_processParallelStack();

        // If only parallel instructions are pending, leave the process method now.
        if($this->current_index < 0)
            return;

        repeat:
        if($instruction = $this->instructions[$this->current_index] ?? NULL) {
            $instruction->process($this->current_index);

            if($instruction instanceof ParallelInstructionInterface) {
                if(!$instruction->isInstructionCompleted())
                    $this->parallelInstructionStack[$this->current_index] = $instruction;
            }

            if($instruction instanceof JumpInstructionInterface) {
                $this->current_index = $instruction->getNextInstructionIndex();
                return;
            }

            if($instruction instanceof SyncInstructionInterface) {
                if(!$instruction->release())
                    return;
            }

            // By default, increase index and continue processing the registered instructions.
            $this->current_index++;
            goto repeat;
        } else {
            // Set to -1 as signal, no more registered instructions are pendent.
            $this->current_index = -1;
        }
    }

    /**
     * @inheritDoc
     */
    public function processCompleted(): bool
    {
        return $this->current_index < 0 && count($this->parallelInstructionStack) < 1;
    }

    /**
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->ready;
    }

    /**
     * @return array
     */
    public function getAddressInstructions(): array
    {
        return $this->addressInstructions;
    }

    public function count()
    {
        return count($this->instructions);
    }

    /**
     * @return InstructionInterface[]
     */
    public function getParallelInstructionStack(): array
    {
        return $this->parallelInstructionStack;
    }
}