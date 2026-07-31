# WeChat distribution

**Status: idea only — not committed, nothing built.** Captured 2026-07-31 from a
design conversation. This note records *feasibility*, not a decision.

## The idea

An automation that watches own-bay listings and shares an **abstract of each new
listing (with a link back to the original) into WeChat, filtered by geolocation
proximity** — the source site, the reference location + radius, and the WeChat
destination all being parameters the operator sets.

Proximity has real data to work with: listings already carry coordinates — see
[[Location feature]]. Any WeChat surface **must honour that feature's privacy
model**: only listings with a *shareable* location (`precision != none`), and
only ever the **rounded, published** coordinate the seller consented to — never
the full-precision value.

## The hard part is "WeChat", not the automation

Pulling listings, computing haversine proximity, de-duping and formatting an
abstract are all trivial. The entire feasibility question is **which WeChat
surface can receive the content, and what it costs to reach it.** There are
three, and they trade off reach against effort in opposite directions.

## Channel 1 — WeCom group robot (企业微信 群机器人)

WeCom / "WeChat Work" is Tencent's *business* messaging app — a separate product
from personal WeChat that interoperates with it at the edges.

- **Enrol:** register an organisation at `work.weixin.qq.com` with a personal
  WeChat account + phone. An **unverified** org is free and instant but
  member-capped and can't use the customer-facing APIs. **Verification (认证)**
  needs a PRC business licence (营业执照) + a fee — a gate we clear (see
  *Operator's position* below).
- **The robot:** a group admin adds a "group robot" and gets a **webhook URL**;
  you `POST` JSON to it. **The URL is the whole credential** — no app id/secret.
  Message types include a **news card** (title + abstract + thumbnail + link),
  which is exactly the shape this idea needs. Rate limit ≈ 20 msg/min.
- **Which groups it can reach — the decisive limit:**

| Group type | Members | Auto-post by webhook robot? |
|---|---|---|
| Internal WeCom group | your org's WeCom users | **Yes** — native, free, unverified org suffices |
| Customer group (客户群 / 外部群) | WeCom members **+ invited personal-WeChat users** | **Not with the simple webhook.** Needs the verified-org **customer-contact (客户联系) API**, and Tencent keeps a human in the loop — external broadcasts (群发) are rate-limited (≈once/day per customer) and often need a member to tap *send*. Deliberately anti-spam. |
| Pure personal-WeChat group | only personal-WeChat users, no WeCom | **No** — no bridge exists, for any bot |

- **Verdict:** cheap and unattended **only for a curated circle** that joins a
  WeCom group. It cannot fan out to the personal-WeChat public.

## Channel 2 — Mini-program (微信小程序)

A sub-app inside *personal* WeChat — this is the one that reaches the real
public, but it inverts the idea from **push** to **pull**.

- **Reach:** discoverable by search, share, QR, and **"附近的小程序" (Nearby Mini
  Programs)**. Native geolocation (`wx.getLocation` + map) makes "listings near
  me" first-class — the *right product shape* for proximity browsing.
- **Enrol / category:** individual-tier mini-programs are barred from
  transactional / marketplace categories, so a second-hand-goods app needs the
  **enterprise tier** (business licence) plus category qualification. Whether a
  **non-commercial** own-bay escapes the "e-commerce" classification is the key
  open question (below).
- **Backend blocker specific to own-bay:** a mini-program may only call
  **server domains whitelisted in the console, over HTTPS, ICP-filed (备案) in
  China**. own-bay runs on an **offshore shared host** with (almost certainly)
  no ICP filing → its API can't be called as-is. Would need a China-ICP-filed
  domain/proxy in front.
- **Push is the weak point:** no free push. Only **订阅消息 (subscription
  messages)** — the user taps to consent **per message** (one tap = one send);
  "long-term" subscriptions exist only for regulated categories. So
  "automatically posts an abstract" degrades to "user opts in, once, for one
  notification." The exact thing a mini-program is *worst* at.
- **Verdict:** the right home for **proximity browsing**, but a real, reviewed,
  filed **product** — and it cannot auto-push, which was the heart of the idea.

## Channel 3 — Official / Service Account (公众号 / 服务号)

For completeness: a **verified Service Account** can push a few template/mass
messages per month, but **only to its own followers, not to groups**, and also
needs the enterprise entity. Push-capable, but wrong audience shape for
proximity-targeted sharing.

## Comparison

| | WeCom robot | Mini-program | Service Account |
|---|---|---|---|
| Reaches personal-WeChat public | No (one group) | **Yes** | Followers only |
| Geolocation browse | n/a | **Native** | n/a |
| Auto-push abstracts | **Yes** (the group) | No (per-msg opt-in) | A few/month, to followers |
| Entity needed | free tier works | enterprise + licence | enterprise + licence |
| China-ICP-filed backend | No | **Yes** — blocks offshore own-bay | for served content |
| Effort | a script + webhook | a filed, reviewed product | account + review |

**The structural catch:** no free/lightweight route both **reaches the
personal-WeChat public** *and* **auto-pushes**. Tencent gates every public-reach
channel behind a Chinese business entity and puts a human/consent step on every
unsolicited push. The cheap version is inherently "curated circle" (WeCom); the
public version is inherently "a real, filed product" (mini-program).

## Operator's position (2026-07-31)

Facts that change the picture, from Pietro:

- **Owns a Chinese small enterprise** (< 1,000,000 CNY revenue, **registered for
  service only**). This **clears the business-entity wall** for WeCom
  verification, enterprise mini-program registration, and a Service Account.
- **Has a direct Tencent contact** — so the category question can be settled
  **authoritatively rather than guessed**.

This shifts the blocker from *"can we even register?"* (answered: yes) to
**"does a *service-scoped* licence + a *non-commercial* own-bay qualify for the
marketplace/classifieds category — or can it be classed as an information/service
tool?"** own-bay's non-commercial nature (listings + discovery, **no
on-platform payment**) is the strongest argument for the service classification.

## Open questions to put to Tencent

1. Can a **service-scope** (non-e-commerce) 营业执照 host a **mini-program** that
   lists second-hand goods for **discovery only, with no on-platform
   transaction**? If not, what scope/qualification is the minimum?
2. Does a **non-commercial** framing avoid the e-commerce category review that a
   marketplace normally triggers?
3. For an **offshore backend**, is a China-ICP-filed **proxy/domain** in front of
   own-bay acceptable, or must the origin itself be filed?
4. For reaching personal-WeChat users via a **customer group (客户群)**, what are
   the *current* verified-org requirements and the external-broadcast rate/human
   -in-the-loop rules?

## Privacy — a new publication surface (must not be skipped)

Forwarding listing abstracts **that include location** into any WeChat surface is
a **new place own-bay publishes seller data** — one no seller has agreed to, even
rounded. Before anything ships this must have a consent/disclosure decision, and
own-bay's privacy statement likely needs a line. This is the "what does this now
publish that it didn't before?" check applied at the design stage. Ties directly
to the precision model in [[Location feature]].

## Caveat — volatile policy

WeCom org-tier caps, verification fees, mini-program category rules, ICP
requirements and customer-group broadcast limits are **policy Tencent revises
often**. Everything above is the shape of the problem, not a spec — re-check
against current `work.weixin.qq.com` / `mp.weixin.qq.com` docs (and the Tencent
contact) before committing to any build.
