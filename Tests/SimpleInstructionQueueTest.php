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
use TASoft\InstructionQueue\Exception\DuplicateAddressInstructionException;
use TASoft\InstructionQueue\Exception\ImmutableInstructionQueueException;
use TASoft\InstructionQueue\Exception\InvalidReferenceException;
use TASoft\InstructionQueue\Instruction\AddressInstruction;
use TASoft\InstructionQueue\Instruction\JumpInstruction;
use TASoft\InstructionQueue\Instruction\ReturnInstruction;
use TASoft\InstructionQueue\SimpleInstructionQueue;

class SimpleInstructionQueueTest extends TestCase
{
    public function testStaticQueue() {
        $q = new SimpleInstructionQueue();
        $q->add($f = function() {
            echo "1";
        });
        $q->add($i = new AddressInstruction("addr"));
        $q->add([$f, 1]);
        $q->add([1, $f]);

        $this->assertCount(4, $q);

        $q->removeInstruction($i);
        $this->assertCount(3, $q);

        $q->setInstructions([$i]);
        $this->assertCount(1, $q);
    }

    /**
     * @expectedException
     */
    public function testImmutability() {
        $q = new SimpleInstructionQueue();
        $q->add($f = function() {
            echo "1";
        });
        $q->ready();

        $this->expectException(ImmutableInstructionQueueException::class);

        $q->add($f = function() {
            echo "1";
        });
    }

    public function testUncompleteAssignment() {
        $q = new SimpleInstructionQueue();
        $q->add($a1 = new AddressInstruction("addr1"));
        $q->add($a2 = new AddressInstruction("addr2"));

        $this->expectException(TypeError::class);
        $this->assertEquals(0, $a1->getIndex());
    }

    public function testIndexAssignment() {
        $q = new SimpleInstructionQueue();

        $q->add($a1 = new AddressInstruction("addr1"));
        $q->add($a2 = new AddressInstruction("addr2"));

        $q->ready();

        $this->assertEquals(0, $a1->getIndex());
        $this->assertEquals(1, $a2->getIndex());

        $this->assertEquals([
            'addr1' => $a1,
            'addr2' => $a2
        ], $q->getAddressInstructions());
    }

    public function testDuplicateAddressInstructions() {
        $q = new SimpleInstructionQueue();

        $this->expectException(DuplicateAddressInstructionException::class);

        $q->add(new AddressInstruction("addr"));
        $q->add(new AddressInstruction("addr"));
    }

    public function testJumpAddressInstructions() {
        $q = new SimpleInstructionQueue();

        $q->add(new AddressInstruction("addr"));
        $q->add($j = new JumpInstruction('addr'));

        $q->ready();

        $this->assertEquals(0, $j->getNextInstructionIndex());
    }

    public function testJumpAddressInstructions2() {
        $q = new SimpleInstructionQueue();

        $q->add(new AddressInstruction("addr"));
        $q->add($j = new JumpInstruction('addr'));

        // $q->ready();

        $this->expectException(TypeError::class);
        $this->assertEquals(0, $j->getNextInstructionIndex());
    }

    public function testJumpAddressInstructions3() {
        $q = new SimpleInstructionQueue();

        $q->add(new AddressInstruction("addr"));
        $q->add($j = new JumpInstruction('inexisting_address'));

        $this->expectException(InvalidReferenceException::class);

        $q->ready();
    }

    public function testSingleRunQueue() {
        $q = new SimpleInstructionQueue();
        $q->add($f = function() {
            echo "1";
        });
        $q->add(new AddressInstruction("addr"));
        $q->add([$f, 1]);
        $q->add([1, $f]);

        $q->process();

        $this->assertTrue($q->processCompleted());
        $this->assertEquals("111", $this->getActualOutput());
    }

    public function testReturnInstruction() {
        $q = new SimpleInstructionQueue();

        $q->add(function() {echo "1"; });
        $q->add(function() {echo "2"; });

        $q->add(new ReturnInstruction());

        $q->add(function() {echo "3"; });

        $q->process();

        $this->assertTrue($q->processCompleted());
        $this->expectOutputString("12");
    }
}
