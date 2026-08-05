# CRUD recipes — worked examples

Each recipe shows the *thinking*, then the *operations*. The goal is to act decisively with the
fewest tool calls, reading first only when you genuinely don't know the target.

## 1. Change a visible heading
> "Change the hero heading to 'Welcome to Money Hill'."

Thinking: this is an UPDATE of a node value. The component is probably "Hero"; the node is the one
labelled like a heading (Heading / Title / Headline).
Operations:
1. (If unsure the component/label exists) `list_components` to confirm the Hero component.
2. `update_node_value` { component: "Hero", label: "Heading", value: "Welcome to Money Hill" }
Reply: "Set the Hero heading to 'Welcome to Money Hill'."

## 2. Add a new field to a block
> "Add a subtitle under the hero heading."

Thinking: ADD a node. A subtitle belongs to the Hero component; it can nest under the heading node.
Operations:
1. `add_node` { component: "Hero", label: "Subtitle", type: "text", value: "", parent: "Heading" }
Reply: "Added a Subtitle field to the Hero, nested under the heading."

## 3. Change a price (use the right type)
> "Make the Pro plan $29."

Thinking: prices are `number`. If the node exists, UPDATE; if not, ADD as `number`.
Operations:
1. `update_node_value` { component: "Pricing", label: "Pro Price", value: "29" }
   (or `add_node` … type: "number" if the field doesn't exist yet)
Reply: "Set the Pro plan price to 29."

## 4. Swap an image
> "Use the new logo I uploaded."

Thinking: the logo is an `image` node; its value is a media URL/path on this site.
Operations:
1. `update_node_value` { component: "Header", label: "Logo", value: "/storage/media/<site>/logo.png" }
Reply: "Updated the header logo."
(If you don't know the media path, ask: "What's the file name of the logo in Media?")

## 5. Create a page, then build it
> "Add an About page with a hero that says 'Our Story'."

Thinking: CREATE the page, CREATE a component WITH the page attached, ADD the heading node with the value.
Operations:
1. `create_page` { name: "About" }
2. `create_component` { name: "About Hero", page: "About", description: "Top of the About page" }
3. `add_node` { component: "About Hero", label: "Heading", type: "text", value: "Our Story" }
Reply: "Created an About page with an 'About Hero' block headed 'Our Story'."

## 6. Publish / unpublish
> "Take the Pricing page offline."
Operations: `publish_page` { page: "Pricing", published: false }
Reply: "Pricing is now a draft."

## 7. Apply a template (destructive — confirm)
> "Rebuild this site from the nexonix template."

Thinking: `apply_template` REPLACES all pages and components, and is owner/admin-only.
Operations:
1. If intent is clear, `apply_template` { template: "nexonix" }. If ambiguous, ask:
   "That replaces all current pages and components — go ahead?"
Reply: "Applied the Nexonix template — pages and components were rebuilt."

## 8. Build a repeating structure with nesting
> "Add three feature cards to the homepage."

Thinking: make a parent node "Cards", then child nodes for each card's title/body pointing at it.
Operations:
1. `add_node` { component: "Features", label: "Cards", type: "text", value: "" }      # parent
2. `add_node` { component: "Features", label: "Card 1 Title", type: "text", value: "Fast", parent: "Cards" }
3. `add_node` { component: "Features", label: "Card 1 Body",  type: "text", value: "…",     parent: "Cards" }
   …repeat for cards 2 and 3.
Reply: "Added three feature cards under a 'Cards' group on the Features block."

## 9. Create a page about a topic with real content (MOST IMPORTANT RECIPE)
> "Create an About Us page for a dentist."
> "Build a landing page for a coffee shop."
> "Add a Services page for a law firm."

Thinking: The user wants a page AND populated content about that topic. This ALWAYS requires chaining: create_page → create_component(s) with `page` param → many add_node calls with real values. NEVER stop after create_page alone. ALWAYS pass `page` to every `create_component` call so the component is attached to the page.

Example — "create an About Us page for a dentist":
1. `create_page` { name: "About Us" }
2. `create_component` { name: "About Hero", page: "About Us", description: "Hero section for the About page" }
3. `add_node` { component: "About Hero", label: "Heading",    type: "text",  value: "Compassionate Dental Care You Can Trust" }
4. `add_node` { component: "About Hero", label: "Subheading", type: "text",  value: "Serving families in the community for over 15 years" }
5. `add_node` { component: "About Hero", label: "Body",       type: "text",  value: "At Bright Smile Dental, our mission is to make every visit comfortable, affordable, and effective. Our experienced team uses the latest technology to keep your whole family smiling." }
6. `create_component` { name: "Our Services", page: "About Us", description: "Dental services overview" }
7. `add_node` { component: "Our Services", label: "Heading",  type: "text",  value: "What We Offer" }
8. `add_node` { component: "Our Services", label: "Service 1",type: "text",  value: "General & Preventive Dentistry" }
9. `add_node` { component: "Our Services", label: "Service 2",type: "text",  value: "Teeth Whitening & Cosmetic Procedures" }
10. `add_node` { component: "Our Services", label: "Service 3",type: "text",  value: "Orthodontics & Invisalign" }
11. `create_component` { name: "Doctor Profile", page: "About Us", description: "Lead dentist bio" }
12. `add_node` { component: "Doctor Profile", label: "Name",      type: "text", value: "Dr. Sarah Mitchell, DDS" }
13. `add_node` { component: "Doctor Profile", label: "Bio",       type: "text", value: "Dr. Mitchell graduated from the University of California School of Dentistry and has been dedicated to patient care for over a decade. She specialises in cosmetic and restorative dentistry." }
14. `add_node` { component: "Doctor Profile", label: "Quote",     type: "text", value: "Every smile tells a story — we help make yours a great one." }

Reply: "Created your About Us page with an About Hero, Our Services, and Doctor Profile sections, all populated with dental content."

Key rules:
- Always use real, appropriate text for the requested industry/topic. Do not leave node values empty.
- Always pass `page: "<page name>"` to every `create_component` call when building page content.

## 10. Create a form with fields in one flow
> "Make a contact form with name, email and message."
> "Create a booking form with name, date, phone, and service type."

Thinking: create_form, then add_form_field for each field. Infer types: email→email, phone→tel, date→date, message/notes/comment→textarea, choice/type→select; everything else→text.

Example — "make a contact form with name, email, and message":
1. `create_form` { name: "contact", title: "Contact Us" }
2. `add_form_field` { form: "contact", label: "Full Name",      type: "text",     required: true }
3. `add_form_field` { form: "contact", label: "Email Address",  type: "email",    required: true }
4. `add_form_field` { form: "contact", label: "Message",        type: "textarea", required: true }

Reply: "Created a Contact Us form with Name, Email, and Message fields."

## 11. "Create content for an existing page" — find first, then build
> "Create content for the About Us page that describes the mission statement."
> "Add content to the Home page about our services."
> "Populate the Contact page."

Thinking: The user is pointing at a **named existing page**. Do NOT create a new page with a made-up name.
Find the page first, then add components and nodes with real content.

Operations:
1. `list_pages` — check if "About Us" already exists.
   - If it exists: skip create_page entirely.
   - If it does NOT exist: `create_page` { name: "About Us" }
2. `create_component` { name: "Mission Statement", page: "About Us", description: "Core mission content block" }
3. `add_node` { component: "Mission Statement", label: "Heading",    type: "text", value: "Our Mission" }
4. `add_node` { component: "Mission Statement", label: "Body",       type: "text", value: "We are committed to delivering exceptional value to our clients through innovation, integrity, and a relentless focus on results." }
5. `add_node` { component: "Mission Statement", label: "Subheading", type: "text", value: "Building trust, one relationship at a time." }
6. `add_node` { component: "Mission Statement", label: "CTA Label",  type: "text", value: "Learn More About Us" }

Reply: "Added a Mission Statement component to the About Us page with Heading, Body, Subheading, and CTA content."

**Key rules:**
- Always `list_pages` before deciding whether to `create_page`. The user named the target page — use it.
- Always pass `page: "<page name>"` to `create_component` so the component is attached to the page.

## Recipe: wireframe a page (plan first, build on approval)

> "Wireframe a SaaS landing page."
> "Sketch out a homepage for an agency."
> "Plan the structure of an About page."

Thinking: The user wants the **structure**, not finished content. Draft a wireframe of catalog blocks
for them to review — do NOT create pages/components yet.

Operations:
1. `list_block_types` — (if unsure of the catalog) lists hero, features, services, pricing, testimonials, team, stats, faq, cta, gallery, contact, newsletter.
2. `create_wireframe` {
     title: "SaaS landing page",
     blocks: [
       { type: "hero" },
       { type: "features" },
       { type: "stats" },
       { type: "pricing" },
       { type: "testimonials" },
       { type: "faq" },
       { type: "cta" }
     ]
   }

Reply: "Drafted a SaaS landing page wireframe with Hero, Features, Stats, Pricing, Testimonials, FAQ, and CTA. Open the Wireframes page to review and Approve & Build it."

Only if the user then says "approve it" / "build it": `commit_wireframe` { wireframe: "SaaS landing page" }.

**Key rules:**
- Blocks are **ordered top-to-bottom** — sequence them like a real page.
- Use only catalog `type` keys. Add an optional `label` to rename a block (e.g. `{ type: "features", label: "Why Us" }`).
- Wireframing never builds content directly — approval (UI button or `commit_wireframe`) does.

## Anti-patterns to avoid
- **NEVER stop after `create_page` alone when content was requested.** Always chain component + node creation.
- **NEVER call `create_component` without `page` when creating content for a page.** The component must be attached.
- Don't dump a long explanation of how the user could do it manually — just do it.
- Don't store prices/links/colours as `text`; use `number` / `url` / `color`.
- Don't apply a template on a vague request — confirm, because it deletes existing structure.
- Don't leave node values empty when the user asked to "populate" or "fill" or "add content about [topic]".
