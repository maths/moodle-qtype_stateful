## Development guidelines for castext2 ##

The CASText-subsystem has been re-implemented multiple times, this time we use a newer parser and aim to compile the CASText syntax into a single pass evaluatable CAS-statement. The blocks of the previous system remain but more blocks do appear and the behaviour of comments and escapes change slightly, more importantly, the values of attributes can now deal with certain key characters like `@` and `#`.

### Primary operating logic ###

Parsed CASText represented as an AST, will be processed node by node using a compiler that turns each node to a CAS-statement. Depending on whether the node represents a block-that needs post-processing that statement may either evaluate to a `"string"` or to a list. If it evaluates to a list then the lists first element is special, as it defines which postprocessing component handles the processing the other elements may be anything the component wants including other CASText.

As an example, lets consider the special `%root`-block and the `commonstring`-block If we had the following portion of CASText:
```
Something... [[commonstring key='your_answer_was_interpreted_as'/]] ...
```
It would be parsed as three distinct things:
 1. The initial portion as raw-string: `"Something... "`
 2. Then a block of the `commonstring` type with a parameter `key` that has the value `your_answer_was_interpreted_as`
 3. At the end would be another raw-string: `" ..."`

As those things represent the root of the CASText we would as the special root-block to compile them and it would simply say that the strings are strings but ask for the `commonstring`-block to compile itself. The result would be:
```
["%root", "Something... ", ["commonstring", "your_answer_was_interpreted_as"], " ..."]
```
In this case, the result has nothing to evaluate, but it could contain `if`-statements and loops tp deal with more complex blocks. Even simple injections like `{@var@}` would generate something interesting there as they would check if their value turns out to be a string and would result in different things. The key thing here is that that result is a complete representation of the original CASText and can be cached to avoid parsing, compiling and validating again.

Once that gets evaluated in CAS the result can be processed. If the result is a raw string then that is the rendered CASText, otherwise, it is a list and the first element will tell which block will handle the postprocessing. In this case, the postprocessing will be done by the special root-block which will concatenate the elements of the list to a single string, as there is one element that is not a string it will find out which block handles its postprocessing and asks it for the processed string. This time the task falls to the `commonstring`-block which will interpret the list so that it takes the second element as a key that needs to be fetched from the localised strings and returns the value of that localised string. In the end, the whole thing turns into a single string, although some blocks may cause some side effects, like the JSXGraph block that adds JavaScript to the page on the side.

### Advanced logic ###

When reading that one may have wondered how the root-block knows which block handles `commonstring`. The answer to that is the `castext2_processor`-interface which allows one to give a directory of blocks to the block that is evaluating things. This interface also allows one to override the evaluation of a given block, which is something one can see happening in the validation-blocks of the input2-system, where the override brings in values from input-validation to the CASText-rendering by overriding the postprocessing of certain blocks.

### New block how-to ###

 1. Place a new file called `NAME.block.php` into the castext2/blocks/ directory.
 2. In the file define a class called `stateful_cas_castext2_NAME` which extends `stateful_cas_castext2_block`.
 3. Implement the `compile`-method and if you cannot evaluate directly to string in the CAS then add a `postprocess`-method.
 4. Validation related methods are needed if you require your block to have specific parameters or if those parameters have a role in CAS. You want to provide validation to give sensible feedback but you do not need to worry about security, that will be handled by the compiler separately.
 5. That is it, feel free to write `[[NAME whatever="something"]]...[[/NAME]]` in your CASText.

If your block has internal structure like the `if-elif-else`-block or special behaviour like the `comment` and `escape` blocks then things get difficult as those would require changes to the parser, don't even think about having such blocks.


## CASText within code ##

There are various places where one may need to work with CASText in context where other things happen at the same time. For example, the custom validation system sens various things to be evaluated to the CAS and some of those things are CASText what it receives can be a mixed list of items that it later processes and may interpret some elements as evaluated CASText needing to be post-processed. Similarily, PRTs may construct feedback from fragments of CASText at the same time as they calculate points and classify the answer, again the return value is not pure CASText. When dealing with obvious uses of CASText like the model-solution one can just ask it to be evaluated and then receives a fully processed end result as a string.

From the question authors point of view there also exists two different ways of dealing with CASText, typically one just writes CASText to fields that supports it and everything just works. However, this is not the only way of workking with CASText, one can also write CASText within the keyvals or the logic portion of the question and then inject that to some normal CASText later. As an example:

 1. Consider a Stateful question with large number of potenttial notes about previous actions. One could have a boolean flag for each potenttial note and conditionally render that note when necessary.
 2. Or one can instead store those notes as CASText or more likely a list of CASText-fragments and just print those out, this also allows you to add values within those notes without having to keep those values in the state separately.
 3. To do that one needs to provide CASText as a Maxima-string through a special function, like here, imagine the `NOTES` is a state-variable:
```
NOTES: append(NOTES, [castext("The expression {@ans1@} was...")]);
``` 
 4. That statement evaluates that CASText with the value of `ans1` at that given moment of execution. One can then output it in normal CASText like this:
```
[[ if test="length(NOTES)>0"]]
<p>Notes about your progress thus far:</p>
<ul>
[[ foreach note="NOTES"]]
  <li>[[ castext evaluated="note" /]]</li>
[[/foreach]]
</ul>
[[/if]]

``` 
 5. One just needs to know that the `[[castext/]]` block is used to inject evaluated CASText fragments and that the `castext()` function evaluated CASText. The latter is not an actual Maxima-function, it is instead a compiler-feature and requires that the parameter it is given is a static-string atom and not a reference to any function or varible.