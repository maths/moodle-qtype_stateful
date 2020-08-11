## The reveal-block ##

The purpose for this block is to provide easy to use stateles features on the client-side for immediate display of content based on specific input values. As this is stateles and the content is in any case present on the client-side do not use these for hints that have a point price. The typical use case for the reveal block is to combine it with an MCQ-input and exposing additional clarifying inputs and guidance based on the selection.

### Example

Imagine that `ans1` is an MCQ input and that `ans2` can be any input. If the student selects from `ans1` the option with value `B` then the contents of the reveal block will become visible. Note that input2 MCQ-fields need to be forced (`mcq-hidden-values` option) to use raw values as normally they anonymise the values, also "string" values need to be properly quoted.

```
<p>[[input:ans1]]</p>

[[reveal input="ans1" value="B"]] 
<p>When selecting B please also give the value X = [[input:ans2]]</p>
<div>[[validation:ans1]]</div>
[[/reveal]]
```

Basically, the reveal block has two parameters 1) the name of the input it keeps an eye on and 2) the raw string value it matches. Note that it only does exact matches and thus best works with MCQ-inputs where the value is in a predefined form. If connected to a student-provided answer type of an input might not work as well as there might be spaces or permuted ordering.


## Potential future plans

The reveal block is quite simple and powerful, but there are obvious weaknesses in it's logic. For example in the initial form it only does exact matching with input-values. Obviously, it cannot do CAS-equivalence style matching but maybe regular expressions could be used to define the match. 

Also it might make sense to have the option of triggering a reveal based on input validation messages or feedback messages instead of just input values, or more likely the existence of some specific content in them, such content identification could be done by for example searching the question contents for the presence of a given CSS-selector matching nodes. For example it should be easy to check if an element of the a given class has appeared. This logic would allow the reveal condition to be evaluated on the server side based on the full input and CAS capabilities and for the server side to return multiple "flags" that the reveal blocks could match.
