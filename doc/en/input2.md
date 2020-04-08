## Development guidelines for input2 ##

The new input system aims to abstract inputs away from general question handling. The objective is that the details of inputs do not appear at the question.php level in any other way than asking the input system to initialise itself, validate a given set of responses, and finally render the validation messages for them.

### Input-type ###

The input2-system assumes that not all inputs are equal, some may never generate a CAS-evaluatable value and others do not require validation-messages. To deal with this there exist multiple interfaces that an input type may implement depending on what it needs to do in the life-cycle of an input. In general, most inputs have options and most do evaluate some options in the CAS during initialisation and the initialisation statement is something that may be cached or not.


### Input-type options ###

The input2-system allows inputs to freely define what options apply to them and also have those options to have CAS-values or even CASText values that get evaluated in the initialisation phase or during validation depending on the needs of the input.

As the inputs can freely define options we need to do some coordination to avoid excessive contradictory options from being collected to the system. Therefore it is highly recommended that one follows the following guidelines.

 1. Use dashes in the option names for a consistent style, not `forbidwords` or `forbid_words` instead have `forbid-words`. It is known that this makes it slightly less easy to work with the keys in object form as opposed to underscores, but that is not an objective in this design.
 2. Never worry about the length of the option name, practically no one will ever write it by hand but a tool might show it and being able to understand the meaning of the option from the name is of value.
 3. Try to check the generic base-classes for options they define and use those if applicable. Especially, their options about validation-messages and the concepts of empty-values.
 4. Try to identify which options are specifically about your type of input and which are generic. If they are input type-specific please prefix them with the type, for example the `mcq-` prefixed options for the MCQ-type and `forbid-` type of generic options specifically forbidding something.
 5. The following prefixes are to be used for their obvious use cases, if you create a new option that can be mapped to these someone will be disappointed: `allow-`,  `fix-`, `forbid-`, `must-`, `require-`, `split-`.
 6. Always define a clear default-value.
 7. Remember that the options are typed and the types do allow constraints. However, note that currently CAS-evaluated things are strings and we do not really provide rules for type-constraining them. In any case, if you are using a static integer value for something define the minimum and maximum values or make it an enum style thing. Please avoid using strings if possible. If a boolean works then go with it, unless it would lead to contradictory situations with other options.
 8. Note that option-values need not be mere singleton-values, they may just as well be lists. Just see the definitions of `mcq-options` or `matrix-columns`.

#### Special options ####

Should an option named `must-verify` exist and have a *true* value then the option `validation-box` better exist and have a non-empty string value. These are defined in the general base class for inputs that have options and can be safely assumed to have validation logic attached to them in various layers of the system and editors outside of it.