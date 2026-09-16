---
paths:
  - 'app/**/*.php'
---

# App

## PHP docblock conventions
Every class, interface, enum, trait, method, property and class constant carries a docblock. Enum cases and promoted constructor properties are exempt unless the name and type do not carry the meaning.

- Open with one present-tense sentence saying what the thing is or does. Never restate the signature: "Serialized constructor.", "Render the view.", "Submit the form." are failures, not examples.
- Describe current behavior only. No history: no "used to", "previously", "no longer", no naming a past refactor or the duplication a class replaced. A reader arriving today does not need the before-picture, and an agent reading it spends context on a state that no longer exists.
- Rationale belongs in a docblock only when it is a standing constraint a future editor would otherwise break. Keep it to a short bulleted list under the summary, never multi-paragraph narrative.
- Never use @since. Keep @param/@return/@throws only where they add a type the signature cannot express (array shapes, generics, thrown types).
- Prefer {@see Class::method()} over prose describing where something lives.
- Prefer docblocks over inline comments; reserve // for exceptionally complex logic.
