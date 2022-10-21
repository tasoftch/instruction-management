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

namespace TASoft\InstructionQueue\Loader;


use TASoft\InstructionQueue\Exception\BadInstructionException;
use TASoft\InstructionQueue\InstructionQueueInterface;
use TASoft\InstructionQueue\Loader\InstructionFactory\InstructionPreflightFactoryInterface;
use TASoft\InstructionQueue\Loader\InstructionSet\InstructionSetInterface;
use TASoft\InstructionQueue\Loader\Model\InstructionData;

abstract class AbstractBinaryDataLoader implements LoaderInterface
{
    /**
     * @return InstructionSetInterface
     */
    abstract protected function getInstructionSet(): InstructionSetInterface;

    /**
     * @return InstructionData[]
     */
    abstract protected function getInstructionModels(): array;

    /**
     * @inheritDoc
     */
    public function load(InstructionQueueInterface $instructionQueue)
    {
        $set = $this->getInstructionSet();

        foreach($this->getInstructionModels() as $data) {
            $f = $set->getInstructionFactory($data->getInstructionName());
            if($f) {
                $i = $f->makeInstruction($data);
                if($i)
                    $instructionQueue->addInstruction($i);
            } else {
                $n = $data->getInstructionName();
                throw (new BadInstructionException("Unknown instruction $n"))->setInstructionModel($data);
            }
        }
    }

    /**
     * Preflights the instruction model and calculates the count of required process steps to complete the full model
     * This method returns -1 if one or more instruction factories don't provide the preflight feature
     * @return int
     */
    public function preflight(): int {
        $count = 0;
        $set = $this->getInstructionSet();
        foreach($this->getInstructionModels() as $data) {
            $f = $set->getInstructionFactory($data->getInstructionName());
            if($f) {
                if($f instanceof InstructionPreflightFactoryInterface) {
                    $count += $f->getProcessCycleCount($data);
                } else
                    return -1;
            } else {
                $n = $data->getInstructionName();
                throw (new BadInstructionException("Unknown instruction $n"))->setInstructionModel($data);
            }
        }
        return $count;
    }
}