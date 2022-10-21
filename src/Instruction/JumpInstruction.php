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

namespace TASoft\InstructionQueue\Instruction;


use TASoft\InstructionQueue\AbstractInstructionQueue;
use TASoft\InstructionQueue\Exception\InvalidReferenceException;
use TASoft\InstructionQueue\InstructionQueueInterface;

class JumpInstruction implements InstructionInterface, InstructionQueueDidLoadNotificationInterface, JumpInstructionInterface
{
    /** @var string */
    private $reference;
    /** @var int */
    private $index;

    /**
     * JumpInstruction constructor.
     *
     * The reference must be an address instruction in the same instruction queue.
     *
     * @param string $reference
     */
    public function __construct(string $reference)
    {
        $this->reference = $reference;
    }


    /**
     * @inheritDoc
     */
    public function process(int $index)
    {
        // Nothing to do, just waiting
    }

    /**
     * @inheritDoc
     */
    public function instructionQueueDidLoad(InstructionQueueInterface $queue)
    {
        if($queue instanceof AbstractInstructionQueue) {
            if($i = $queue->getAddressInstructions()[$this->getReference()] ?? NULL) {
                $this->index = $i->getIndex();
            }
        } else {
            $instructions = $queue->getInstructions();
            array_walk($instructions, function($i) {
                if($i instanceof AddressInstruction) {
                    if($i->getAddress() == $this->getReference()) {
                        $this->index = $i->getIndex();
                    }
                }
            });
        }

        if(NULL === $this->index)
            throw (new InvalidReferenceException("No address instruction %s found", 0, NULL, $this->getReference()))->setReference( $this->getReference() );
    }

    /**
     * @return string
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @inheritDoc
     */
    public function getNextInstructionIndex(): int
    {
        return $this->index;
    }
}