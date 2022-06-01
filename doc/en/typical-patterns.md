## Notes about basic question patterns ##

While it may be easy to make a question move from a scene to another one may benefit from knowing about the following observations.

### Pause ###

The logic will switch scene instantly, i.e., once the PRT says that a transition is to happen the next thing the student sees is the new scene. This is often a reasonable behaviour as long as the next scene contains enough content to let the student catch up and see what just happened. Basically, not telling them that they just correctly did something or did something wrong too often is a bit rude.

So it is a common pattern to add a boolean state variable signaling that a transition is to happen and make the current scene so that the normal content becomes innactive (inputs no longer present or disabled, but possible having a modified form of the origina content) if that flag is up as well as a having a new "continue"-button appear when that happens, preferably next to a text describing what happened and what is about to happen. The button will simply trigger a simple PRT that does the original scene transition, whatever other state changes are needed can be done in the same logic that raised this flag.

### End scenes ###

It is a good idea to have an end scene, to make it clear to everyone that nothing more is to happen. Maybe give extra feedback about the performance or point towards materials related to the subject in these scenes. As long as the scene has no inputs it is an end scene.

### The review loop details ###

In short a review loop is a construct where a singular question can be attempted and if one fails in those attempts too often one ends up in another question or a sequence of questions that need to be completed before one returns to the original question to attempt it again. Typically once the original question gets the correct answer one moves forward to the next thing, but it is possible that the loop is tied into the last or only question of the question.

Basically, the review loop portion is an exercise material within the question and may consist of smaller and simpler questions reviewing the relevant concepts for the issue at hand. Alternatively, it may be a step by step guided solution process for that very same question and it may result in that answer being formed, in this latter form it becomes questionable whether it makes sense to return to the original question to transfer the formaed answer to it, but one can naturally evaluate something similar in the review loop and check if the student can do that same for the actual question, or maybe build the formulas in the review portion and see if the student can plug in the numbers after the loop.

It is not impossible to have separate loops for different types of failures, but that starts to get complicated, and one probably wants to start to consider the loop portion of this pattern as a complete Stateful question with multiple state variables acting as the means for transfering the information needed to deal with different types of failures.

Notes from trials:

 1. One can have an integer counter for the number of tries. Just remember
    to not increment it after you return from the loop and not to go
    back to the loop for a second time. As each increment is a state transfer
    doing extra increments after they are relevant, they may complicate data analysis. Basically, make the loop trigger only when the counter reaches
    the threshold and do a last increment of the counter at that point so that
    you will be over the threshold and only increment if below the treshold.
 2. Integer counters do not track the uniquenes of those attempts, you might
    instead use a set and store the actual input values into it and then look
    at the size of the set. If your question relies on representational 
    details like significant figures adding the raw input strings is often
    a good way for dealign with this.
 3. The treshold for the loop triggering should not be one, i.e., unless 
    the original question is MCQ or somehow trivial one should allow multiple
    failures. In soem cases, displaying the number of failures remaining and
    a notice that a review will trigger may help some students to do those extra tries just to see the review.
 4. There may or may not be a reason to give points for the actions in 
    the review loop. If one gives points there one should make it so
    that the original question won't give full points after the loop so
    that those going for the loop don't get more points than those that
    managed without it. Whether one should be able to get equivalent number
    of total points after the loop is another matter.