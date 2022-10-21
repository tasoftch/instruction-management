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

use PHPUnit\Framework\TestCase;
use TASoft\InstructionQueue\Instruction\AddressInstruction;
use TASoft\InstructionQueue\Instruction\JumpInstruction;
use TASoft\InstructionQueue\Instruction\JumpWithResetInstruction;
use TASoft\InstructionQueue\Instruction\ReturnInstruction;
use TASoft\InstructionQueue\Instruction\WaitForParallelsInstruction;
use TASoft\InstructionQueue\SimpleInstructionQueue;

class ParallelInstructionQueueTest extends TestCase
{
    public function testOneParallelInstruction() {
        $q = new SimpleInstructionQueue();
        $q->add(function() {echo "1"; });
        $q->add([2, function() {echo "P"; }]);
        $q->add(function() {echo "2"; });

        $q->process();
        $this->assertFalse($q->processCompleted());

        $this->expectOutputString("1P2");
        $q->process();
        $this->expectOutputString("1P2P");
        $this->assertTrue($q->processCompleted());

        $q->process();
        $q->process();
        $q->process();
        $q->process();

        $this->expectOutputString("1P2P");
        $this->assertTrue($q->processCompleted());
    }

    public function testParallelJumpingInstructions() {
        $q = new SimpleInstructionQueue();
        $q->add(new AddressInstruction("addr"));

        $q->add(function() {echo "1"; });
        $q->add([6, function() {echo "P"; }]);
        $q->add(function() {echo "2"; });

        $q->add(new JumpInstruction("addr"));
        $q->add(function() {echo "3"; });

        $q->process();
        $this->assertFalse($q->processCompleted());

        $this->expectOutputString("1P2");

        $q->process();
        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2P1P2");

        $q->process();
        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2P1P2P1P2");

        $q->process();
        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2P1P2P1P2P1P2");

        $q->process();
        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2P1P2P1P2P1212");
    }

    public function testParallelJumpingWithResetInstructions() {
        $q = new SimpleInstructionQueue();
        $q->add(new AddressInstruction("addr"));

        $q->add(function() {echo "1"; });
        $q->add([6, function() {echo "P"; }]);
        $q->add(function() {echo "2"; });

        $q->add(new JumpWithResetInstruction("addr"));
        $q->add(function() {echo "3"; });

        $q->process();
        $this->assertFalse($q->processCompleted());

        $this->expectOutputString("1P2");

        $q->process();
        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2P1P2");

        $q->process();
        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2P1P2P1P2");

        $q->process();
        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2P1P2P1P2P1P2");

        $q->process();
        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2P1P2P1P2P1P2P1P2");
    }

    public function testSyncParallelInstructions() {
        $q = new SimpleInstructionQueue();

        $q->add(function() {echo "1"; });
        $q->add([3, function() {echo "P"; }]);
        $q->add(function() {echo "2"; });
        $q->add([6, function() {echo "Q"; }]);

        $q->add(new WaitForParallelsInstruction());

        $q->add(function() {echo "3"; });

        $q->process();
        $this->expectOutputString("1P2Q");

        $q->process();
        $this->expectOutputString("1P2QPQ");

        $q->process();
        $this->expectOutputString("1P2QPQPQ");

        $q->process();
        $this->expectOutputString("1P2QPQPQQ");

        $q->process();

        $this->expectOutputString("1P2QPQPQQQ");
        $this->assertFalse($q->processCompleted());

        $q->process();
        $this->expectOutputString("1P2QPQPQQQQ3");
        $this->assertTrue($q->processCompleted());
    }

    public function testReturnInstruction() {
        $q = new SimpleInstructionQueue();

        $q->add(function() {echo "1"; });
        $q->add([3, function() {echo "P"; }]);
        $q->add(function() {echo "2"; });

        $q->add(new ReturnInstruction());

        $q->add(function() {echo "3"; });

        $q->process();

        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2");

        $q->process();

        $this->assertFalse($q->processCompleted());
        $this->expectOutputString("1P2P");
        $q->process();

        $this->assertTrue($q->processCompleted());
        $this->expectOutputString("1P2PP");
    }
}
