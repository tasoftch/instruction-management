# Instruction Management
Simple package that allows to load instructions into a local memory and execute them by triggering a cyclic process method.  
Each instruction can decide, if the execution process needs to wait for it or if its executable in "background".  
PHP does not support background threads by default, but this management creates a work around for tasks like this. 