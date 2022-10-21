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
use TASoft\InstructionQueue\Exception\FileNotFoundException;
use TASoft\InstructionQueue\Loader\InstructionSet\ChainInstructionSet;
use TASoft\InstructionQueue\Loader\InstructionSet\DirectoryInstructionSet;
use TASoft\InstructionQueue\Loader\InstructionSet\InstructionSetInterface;
use TASoft\InstructionQueue\Loader\Model\InstructionData;

class BinaryFileDataLoader extends AbstractBinaryDataLoader
{
    /** @var string */
    private $version;
    /** @var InstructionSetInterface */
    private $instructionSet;
    /** @var InstructionData[] */
    private $instructionData;

    /**
     * BinaryFileDataLoader constructor.
     * @param string $version
     * @param string $libraryPathPrefix
     */
    public function __construct(string $version, string $libraryPathPrefix = './')
    {
        list($v1, $v2) = explode(".", $version);
        $this->version = $version;
        $lib2 = "$libraryPathPrefix/$v1";
        $lib1 = "$libraryPathPrefix/$v1/$v2";

        $iFactory = new ChainInstructionSet();
        if(is_dir($lib1)) {
            $iFactory->addSet(
                new DirectoryInstructionSet($lib1)
            );
        }
        if(is_dir($lib2)) {
            $iFactory->addSet(
                new DirectoryInstructionSet($lib2)
            );
        }
        $this->instructionSet = $iFactory;
    }

    /**
     * @param string $filename
     * @param bool $replace_current_data
     */
    public function importInstructionData(string $filename, bool $replace_current_data = true) {
        if(!is_file($filename) || !is_readable($filename))
            throw new FileNotFoundException("File does not exist");

        if($replace_current_data)
            $this->instructionData = [];

        $lines = file($filename);
        $hdr = array_shift($lines);
        list($cmd, $sig, $version) = preg_split("/\s+/i", $hdr);
        if($cmd == 'M86' && $sig == 841486) {
            if($this->getVersion() != $version)
                throw new \InvalidArgumentException("Version does not match with loaded library");

            foreach($lines as $line) {
                $line = trim($line);
                $args = preg_split("/\s+/i", $line);
                $cmd = array_shift($args);
                if(!$cmd)
                    continue;

                $d = new InstructionData($cmd, $args);
                if($this->getInstructionSet()->getInstructionFactory($d->getInstructionName())) {
                    $this->instructionData[] = $d;
                } else
                    throw (new BadInstructionException("Bad instruction"))->setInstructionModel($d);
            }
        } else
            throw new \InvalidArgumentException("Can not parse header signature");
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }


    /**
     * @inheritDoc
     */
    protected function getInstructionSet(): InstructionSetInterface
    {
        return $this->instructionSet;
    }

    /**
     * @inheritDoc
     */
    protected function getInstructionModels(): array
    {
        return $this->instructionData;
    }
}