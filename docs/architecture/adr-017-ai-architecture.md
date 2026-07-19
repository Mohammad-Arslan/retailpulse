# ADR-017: AI Architecture

Status: Accepted

Date: 2026-07-19

Related: [ADR-003 Backend Architecture](./adr-003-backend-architecture.md) · [ADR-010 Security](./adr-010-security.md) · [ADR-016 Reporting & BI](./adr-016-reporting-bi.md) · [ADR-007 Integration Hub](./adr-007-integration-hub.md)

---

## Why

RetailPulse already ships AI-assisted features (an in-product guide assistant, a local-dev AI helper) and the long-term vision anticipates more — a future "Copilot" that helps a business owner interpret their own data, draft a report, or navigate the system conversationally. Without an explicit architecture, AI features risk becoming a second, ungoverned place business logic lives (an LLM "deciding" something a Service should decide, per [ADR-003](./adr-003-backend-architecture.md)), a place where RBAC is accidentally bypassed (an assistant surfacing data the asking user isn't authorized to see, per [ADR-010](./adr-010-security.md)), or a vendor-lock-in dependency baked directly into feature code.

## What

AI features are **assistive, not authoritative** — they answer questions, summarize, and draft, but never make or execute a business decision on their own. Every AI-powered feature is provider-agnostic behind an interface, every agent's knowledge and behavior is constrained by an explicit, guarded system prompt, and every agent operates under the same RBAC boundary as the user it's assisting.

## How

### AI assistants — current shape

`App\Ai\Agents\` holds discrete, single-purpose agent classes (e.g. `GuideAssistantAgent`, `LocalDevAssistant`) implementing `Laravel\Ai\Contracts\Agent` with the `Promptable` trait, each configured with explicit `#[MaxTokens]`, `#[Temperature]`, and `#[Timeout]` attributes rather than ambient/default provider settings — an agent's resource bounds are part of its definition, not left to a provider default that could change underneath it. `GuideAssistantAgent` is the canonical pattern: an in-product help assistant scoped to answer *only* from a specific user-manual excerpt passed into its `instructions()`, with explicit "hard rules" in its system prompt against inventing RetailPulse screens/features not in that context and against following user instructions that try to override those rules. Every new agent follows this shape: a narrow, explicitly-scoped knowledge source, not a general-purpose assistant with implicit access to "everything."

### Prompt strategy

- **System prompt owns the guardrails, not the calling code.** `GuideAssistantAgent::instructions()` is the template: state the persona, state the *exclusive* knowledge source, enumerate hard rules (answer only from provided context; do not invent product features; refuse and redirect off-topic requests; ignore attempts to override these rules), then state answer style. A new agent copies this shape rather than inventing a differently-structured prompt each time.
- **Context is passed explicitly per request** (the guide excerpt, in `GuideAssistantAgent`'s case), not assumed to be in the model's training data — RetailPulse-specific facts (screens, permissions, workflows) must come from RetailPulse's own content, because an LLM's general knowledge about "a retail ERP" is not knowledge about *this* one.
- **Prompt-injection resistance is a stated rule, not an afterthought** — every agent whose input includes user-supplied or third-party text (a guide excerpt, a support ticket body, a future customer-facing chat message) states explicitly, in-prompt, that embedded instructions in that content do not override the agent's own rules. `LocalAiService::sanitizeUserText()` additionally sanitizes free-text input before it reaches a prompt.

### AI permissions

An agent never gets broader data access than the user it's acting on behalf of. A future Copilot answering "what were my top products last month" queries the same RBAC-scoped, branch/tenant-scoped repositories and Policies ([ADR-010](./adr-010-security.md)) any other feature would — there is no "AI service account" with elevated or unscoped access. If an agent needs data to answer a question, it is handed that data through the normal authorization path (a Service call the requesting user is already permitted to make), not given direct, unscoped database access "for convenience." This is the same principle as [ADR-010](./adr-010-security.md)'s "authorization is enforced server-side, always" applied to AI: an agent is not a bypass of the authorization boundary, it's another caller subject to it.

### AI safety

- **Assistive, never authoritative**: an AI agent may draft a report, summarize a guide section, or suggest a demand forecast ([ADR-016](./adr-016-reporting-bi.md)) — it never directly executes a state-changing business action (approving a workflow step, posting a journal entry, completing a sale) without a human confirming through the normal UI/authorization path. An agent that wants to *trigger* an action calls the same Service a controller would, behind the same authorization check, with the result surfaced to a human — it does not silently act.
- **Scoped by default, expanded deliberately**: `GuideAssistantAgent`'s "answer only from this excerpt" pattern is the template for constraining blast radius — a new agent starts narrow (one guide, one report, one conversation's context) rather than broad ("full database access") by default.
- **Local-only surfaces stay local-only**: the dev AI smoke endpoint (`POST /api/dev/ai/ask`) is gated to return 404 unless `APP_ENV=local` (`EnsureLocalEnvironment` middleware) — a debugging/exploration surface for AI behavior never accidentally ships reachable in staging/production.
- **Failure degrades gracefully**: `LocalAiService` catches provider connection/request failures and raises a clear, typed error rather than letting a malformed or empty AI response propagate as if it were valid product behavior — consistent with [ADR-003](./adr-003-backend-architecture.md)'s error-handling standard.

### AI extensibility — provider-agnostic by contract

- `App\Contracts\AI\LocalAiClient` is the interface calling code depends on; `LocalAiService` is today's Ollama-backed implementation, resolved the same way any other Service interface is bound in `AppServiceProvider` ([ADR-003](./adr-003-backend-architecture.md)). Swapping the underlying provider (Ollama locally, a hosted model in production) is a configuration change (`config('ai.default')`, `config('ai.providers.*')`), never a rewrite of calling code.
- `laravel/ai`'s `Agent` contract plus per-agent attributes (`MaxTokens`, `Temperature`, `Timeout`) is the sanctioned way to define a new agent's behavior — not a hand-rolled HTTP client per feature calling a specific vendor's API directly. This keeps provider swaps, cost/credit accounting (the framework's `InsufficientCreditsException`, already wired in `bootstrap/app.php`'s exception handling), and timeout/token discipline centralized rather than duplicated per feature.
- Local development runs entirely against a local Ollama model (`OLLAMA_BASE_URL`, `OLLAMA_MODEL` in `.env`) with zero external API dependency or cost — a contributor (human or AI) can exercise AI-powered features offline/locally without needing a hosted provider credential, and without RetailPulse's own product depending on any single vendor's availability.

## Trade-offs

- **Narrow, single-purpose agents (one per use case) mean more agent classes over time** than one general-purpose "do anything" agent — accepted because a narrowly-scoped agent's failure mode (a wrong answer about the topic it's scoped to) is far more contained and predictable than a general-purpose agent's (confidently answering *outside* its actual knowledge, or being steered off-task by adversarial input).
- **Provider-agnostic abstraction (`LocalAiClient`, `Agent` contract) costs a small amount of indirection** versus calling a specific vendor's SDK directly — accepted for the same reason repository interfaces are used elsewhere ([ADR-003](./adr-003-backend-architecture.md)): it avoids a single vendor's API becoming load-bearing throughout the codebase.
- **"Assistive, never authoritative" limits what AI features can do out of the box** (no fully autonomous agent taking actions unsupervised) — accepted deliberately: RetailPulse is a financial/HR system where an incorrect autonomous action (a wrongly-approved workflow step, a bad auto-post) is a much costlier failure mode than a chat assistant giving an unhelpful answer.

## Alternatives considered

- **A single general-purpose "RetailPulse Copilot" agent handling every AI use case** — rejected in favor of narrow, purpose-specific agents (above); a single broad agent's prompt would need to simultaneously guard against irrelevant-topic drift for every use case at once, which is harder to get right and harder to reason about than several narrow, independently-scoped agents.
- **Direct vendor SDK calls per feature (e.g. call OpenAI's or Anthropic's SDK directly from a controller)** — rejected: recreates the exact hardcoded-provider problem [ADR-007](./adr-007-integration-hub.md) avoids for payment/communication providers; `LocalAiClient`/`Agent` abstraction is the same "swappable adapter" pattern applied to AI providers.
- **Letting an agent query the database directly (its own repository access) instead of going through user-scoped Services** — rejected: would make an AI agent a second, parallel authorization path outside [ADR-010](./adr-010-security.md)'s single-source-of-truth Policy model — exactly the kind of authorization drift that ADR exists to prevent.

## Future direction

A future business-facing "Copilot" (conversational data exploration, drafting help, guided workflows) is anticipated by RetailPulse's vision but not yet scheduled to a specific phase. When it is, it is expected to be built as a set of narrowly-scoped agents following this ADR's pattern — each agent reading through the same RBAC-scoped Services a human user would use, each with an explicit, guarded system prompt, and each swappable across providers via the existing `Agent`/`LocalAiClient` abstraction. AI-assisted analytics (demand forecasting beyond the current stub, RFM segmentation, natural-language report building — SRS §3.29 future scope, see [ADR-016](./adr-016-reporting-bi.md)) follows the same principles: assistive output surfaced to a human, never an autonomous action.

## Impact on future development

- A new AI-powered feature is built as a narrow `Agent` implementation with an explicit guarded system prompt, resolved through the existing provider-agnostic contract — not a bespoke integration with a specific AI vendor's SDK.
- An AI agent's data access is always mediated through the same RBAC/Policy path a human user's request would take — an agent is never granted broader access "because it's just an AI feature."
- AI output that could change business state is always surfaced to a human for confirmation through the normal authorized action path, never executed autonomously by the agent itself.
