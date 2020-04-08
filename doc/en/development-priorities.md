## Development priorities & philosophy ##

Number one priority is to maintain compatibility with STACK, it should always be possible to turn a STACK question into a scene in a Stateful question. However, this does not mean that we do everything like STACK, we just need to be able to provide equivalent behaviour.

### Caching & compiling ###

As Stateful questions require plenty of evaluation of states and logic in them when being tested it is of utmost importance to aim to optimise the processing of logic and especially note that validation and parsing of the question definition is not something we can repeat at every step. For this reason, the philosophy is that if we can compile some part of definition into a single CAS-statement and validate it we should store that CAS-statement as a string and use it directly instead of rebuilding and revalidating it later.

### Minimise CAS-calls ###

If at all possible merge all that you can into single CAS calls. In an ideal world questions should only make the following calls:

 0. At the start of use generation of state variable initial values.
 1. Question initialisation, setup of all variables and input initialisation logic as well as the question texts.
 2. Optional input CAS-validation, for inputs that need CAS level validation.
 3. PRT-evaluation, i.e. execution of PRTs for the valid inputs. Also, generate validation messages at the same time.
 4. Test-case initialisation/generation of test inputs.
 5. Test-case evaluation.

4 and 5 are separate as we want to push the generated test input through the full input processing chain and it does not exist in CAS.

### Testing and particularly unit-testing ###

In this project, every test must define what it tests in the sense of which subsystem it tests, so that should a subsystem be exchanged to another implementation, tests related to the old implementations internal working can be thrown away. At a higher level there are then tests that consider such subsystems as black-boxes and only test the expected behaviour required by the whole.

The primary form of testing focuses on executing test-questions and their internal question-tests. Should a new feature be built there must then be a sample-question that shows it in action and can be made to test it. This is not quite end-to-end testing as the required behaviour does not interact with Moodle, these tests test the behaviour of a Stateful-question in any engine executing them not the integration of the engine to the VLE around it, one should, however, aim to make it possible to also test that but the requirements of such test set-up must never lead to addition of testing-specific logic into the engine itself.

In general, the view of the original developer is that it is more important to declare the external expected behaviour than the behaviour of singular function deep in some subsystem that never gets directly accessed by the core logic.

### Sample-questions ###

While it is nice to have sample-materials Stateful should never start carrying large sets of them in the release as maintaining them will take resources. Should there be publicly available materials somewhere we can link to them but taking them into the code-base should not be done.

The sample-materials that Stateful comes with are all to be directly tied to either testing of the system or documentation, no others should exist.

### The GIT-branches ###

Stateful, follows the general rules of STACK here, somewhat adapted and shortened:

 1. `master`-branch is always a functioning release, with hot-fixes applied as needed.
 2. `dev`-branch is where larger development happens, it will always maintain a higher version-number than the master, and is likely to not be in a state that can be reverted from to `master`.
 3. `iss*`-branches store short term developments related to specific issues, they will be merged to `dev` once completed and accepted and in some cases also directly to `master`. All `iss*`-branches will should be deleted once half a year has passed since they last merged `master` or `dev` into them, they can be cleaned of earlier e.g. when they get merged to `dev`.

### Typed languages are better ###

Some may believe the duck-typing is nice, in this project however we will enforce types everywhere we can. So expect to see types in function signatures.



## Major objectives ##

### CASText2 ###

CASText rewrite has been done in Stateful and will be proven here before it gets transferred to STACK and replaces the ageing version there. Key difference is that CASText2 is a single pass evaluatable implementation with some new parser features.

### Compiled PRTs ###

Stateful is the proving ground for guard clauses in PRTs and single-pass evaluation of PRTs using compiled PRTs. Currently this is tied to CASText2 and provides a single statement that evaluates the PRT and the feedback at the same time.

### Redesign of the input system (input2) ###

The current input system has places where CAS-calls are not minimised and it has problems with input types having rich options or options with CAS-values. A full rewrite is probably the best option for untangling the structure. The current idea is a follows:

 1. Input-types have distinct processing phases and the values those phases need to have CAS-evaluated, will be collected into a coordinator that evaluates them in bulk.
 2. Input-options will turn into a free-form JSON-object and the types may use it to store whatever is necessary, they should also provide a Schema for it and some form of definition of the presentation of those options in an editor.
 3. Input-validation messages will be separated from inputs and can be shared by inputs.
 4. CAS-values as parameters for inputs leads to inputs needing to add things to the initialisation session. After that they may add student input related values to an optional validation session and finally validation messages may evaluate additional logic in the evaluation session.

A key objective is to build a coordinator that collects initialisation statements from the inputs for initialisation of the question, pushes the values the question receives to the inputs for validation and finally returns the valid CAS-objects representing the input values for whatever needed them. Also generates and returns the validation messages and rendered inputs for those needing them.

As an example consider one of the MCQ inputs, they have been using the teachers answer field as the place to define the answer options as there is no other place to place CAS-values. They then receive the teachers answer from common validation and parse it just to send it back to CAS to generate some additional details. It would be saner to provide multiple option fields even some that directly handle lists that would contain the answer options and for the input to be able to send those directly to CAS for whatever processing it needs during its initialisation phase among all inputs initialisation values and not every input by itself.

Another example for the separation of validation-messages from inputs is the classic case where one asks for the multipliers for a polynomial in separate fields and the validation is essentially just excessive number of boxes saying that something was interpreted as... Instead it might be nicer to be able to collect all those values into a single validation box and to control their layout and error messages a bit. One could for example render the matrices of a state-space representation into a single system of equations. For this to work we need to have means of plugging in empty input values and invalid values as well as certain common content blocks like lists of variables/units and error listings.

### Attachments ###

The current system does not support attachment files, images etc. It is a priority to allow them but at the current time it is expected that the YAML-API solution for STACK will implement this and Stateful will aim to replicate that. For now should one need to include images they may overcome this limitation by using base64 in the `src`-attribute of `<img/>`-tags. It is not recommended to serve attachments from external sources so inline images and eventually managed attachments are to be aimed for.

### Global state ###

The concept of global state from the original "STACK with state"-prototype will eventually return but before that the problem of storing such state in a manageable way and the annealing processes related to it need to be further designed.


## Minor objectives ##

### Annotations ###

Look into annotation-based typing of variables. i.e. some way to say that apply vector styling to this variable and render that as a matrix, without having to write the texput rules directly and thus allowing declaring styles for all matrices in the question or in a set of questions.

Annotation-based units system declarations, use annotations to declare the system to use and units to add to it instead of directly accessing the deeper structures.
