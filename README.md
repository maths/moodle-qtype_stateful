# Stateful question type 1.2

### Changelog

**1.2.1** Fixes button inputs on newer Moodles, also now in sync with STACK 4.6 which is now the required version.

**1.2** Rest of the move of CASText2 to STACK now with all the `[[textdownload]]` and other features. Removes own `[[reveal]]` block as redundant. **Drops support from pre 4.0 Moodles and requires STACK 4.4.3.** Do note the changes to `[[commonstring]]`, it now targets STACK strings if you used it with STateful strings you need to rename that block to `[[statefulcs]]`.

**1.1** (never released) Move CASText2 over to STACK. Use the new security system and various other things from STACK 4.4. Note! This release does a run-time patch of STACKs CASText2 so that we can keep using the `[[reveal]]`-block (`[[index]]` and `[[indexing]]` are in the same situation), this means that it becomes active on the STACK side as well but won't work there as STACK does not use the same input system. Also we now use a different `[[commonstring]]`-block, if you want to use ours use `[[statefulcs]]`, this unfortunately breaks things that have used the `[[commonstring]]` on our side, but you now get access to both question types localised strings.

**1.0.5** Fix rendering of feedback to follow similar logic as the adaptivemultipart behaviour. This is important for questions implementing "counters" and ensures that students cannot receive feedback without actually submitting values for feedback.

**1.0.4** sync up to STACK 4.3.9, an issue related to the previous validation problem and the state of inputs moving forward while the scene text did not has now been reolved. Also tools for extracting easier to process data for analysis.

**1.0.3** sync up to STACK 4.3.8, fixes some errors in MCQ inputs as well as String tests. As a major fix deals with situations where transitioning to scenes with inputs (simillarily named) that do not require validation.

**1.0.2** adds the order-input type for drag & drop ordering of lists and doing Parsons problems. Also new castext2 blocks index/indexing and reveal. Enabled general-feedback/model-solution.

**1.0** Initial release.



Stateful is a question type based on [STACK](https://github.com/maths/moodle-qtype_stack/) like STACK it's well suited for assessment of STEM subjects. However, the thing that distinguishes it from STACK also opens up usage in other fields.

The point of Stateful is to provide memory within the question logic by using state-variables and thus making it possible to modify the parameters of the question based on inputs received while the student is interacting with it. Essentially, Stateful-questions consist of multiple parametric STACK-like questions that can react to student answers by switching to a different question of that set and setting the parameters accordingly, i.e., the memory/state can represent the parameters of those parametric questions and can be defined based on the students' input.

Stateful was created by [Matti Harjula](http://math.aalto.fi/en/people/matti.harjula) of the Aalto University. Stateful, contains contributions done by Eleaga Ltd Oy, it has also been supported by funding from the Finnish Ministry of Education and Culture.

# Note
**This version does not come with any useful documentation and is only meant for those that receive guidance through other routes, please do not ask for examples or documentation here at this phase.** At some point things will change, but for now consider this as a very specialised tool for people that have exceptional skills with STACK and contacts that provide the missing pieces. The sample-materials may give some hints about what this is all about, but even them are still being workked on.

## Requirements

Stateful requires **PHP 7.1+** and Moodle 4.0+. The PHP-extension `mbstring` is required, and `yaml` helps if one needs to use the minimal editor.

Stateful also requires the Stateful question behaviour and STACK 4.4.3. Expect that Stateful will always require a recent STACK version and that upgrading STACK may mean that Stateful will also need to be upgraded and vice-versa.


## Current state of development

The question type portion (i.e. this repository) and the behaviour are now ready for use. However, they are still being further developed using funding from the Finnish Ministry of Education and Culture. 

The question type does not currently come with a question editor beyond a raw question model editor. The plan is that there will be multiple editors for different types of questions with different types of logics for defining the question, but for now there is no open-source editor present. An editor is not required for running the questions so one can still use Stateful even if one does not have a specialised editor for it. There exists a commercial partner that is working on an IDE for Stateful, it is expected to be the golden standard of editors for quite some time, it is currently unknown when it becomes available and how one gains access to it.

Note these are the key features that are currently missing and are likely to be requested; others exist as well:

 1. Images and attachments are currently not supported, one may use `[[jsxgraph]]` etc., but for now the transfer formats of the question do not allow attached files.
 2. The `equiv` input-type of STACK is not currently present, it should not take long for it to be added.
 3. User-level state as opposed to question attempt level state, it is in planning but requires much planning still. Essentially, learning analytics connections would need to be somehow designed.
 4. Model solutions (general feedback) are currently not displayed, they will appear very soon.


## Stateful in relation to STACK

Stateful will always integrate to STACK; STACK is the engine under the Stateful question type. However, Stateful will happily test new ways for that engine to work, and it is expected that features born and evolved in Stateful may move back to STACK side. One example of such features is the Maxima-parser which originated as a part of Stateful-editor, was then included to be part of the compiler of Stateful-engine and finally filled a hole in STACKs core in version 4.3.

As to which should be used to author questions, well if you do not have a real need for memory/state in your question, use STACK to minimise the number of moving parts. Stateful will never support deferred-feedback like STACK does, and will thus never work in those use cases.


## License

Stateful-engine and Stateful-behaviour are Licensed under the GNU General Public, License Version 3. The licensing of various Stateful-editors is up to those editors.
