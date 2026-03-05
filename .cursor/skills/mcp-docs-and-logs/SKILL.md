---
name: mcp-docs-and-logs
description: Instructs the agent to use Context7 MCP (user-context7) for PHP, Apache, Laravel, HTML, CSS, JavaScript and library documentation; use Apache Logs MCP (user-apache-logs) for errors, logs and server debugging. Use when the user asks about these technologies, documentation, or about errors, logs or debugging server issues.
---

# MCP Tools: Context7 and Apache Logs

Use the appropriate MCP server and tools for documentation vs. errors/logs.

## When to use Context7 (user-context7)

Use when the user asks about **PHP, Apache, Laravel, HTML, CSS, JavaScript** or **any library/framework documentation**.

**Tools:**

1. **resolve-library-id** — Call first to get a Context7 library ID, unless the user already gave one as `/org/project` or `/org/project/version`.
   - `libraryName`: e.g. "php", "laravel", "apache", "react"
   - `query`: short description of what they're trying to do

2. **query-docs** — Query up-to-date docs and code examples.
   - `libraryId`: from resolve-library-id or user
   - `query`: specific question or task

Call resolve-library-id at most once per library; call query-docs at most 3 times per question.

## When to use Apache Logs (user-apache-logs)

Use when the user asks about **errors, logs, or debugging server issues** (Apache/PHP stack).

**Tools:**

| Tool | Use for |
|------|--------|
| **get_all_errors** | Both Apache and PHP errors together (no args) |
| **get_php_errors** | PHP errors only; optional `lines` (default 50) |
| **get_apache_errors** | Apache error_log; optional `lines` (default 50) |
| **get_apache_access** | Apache access_log; optional `lines` (default 30) |

Prefer **get_all_errors** for general "what’s wrong" or "show me errors"; use the specific tools when the user needs only PHP or only Apache logs, or access log for request debugging.

## Invocation

- Use `call_mcp_tool` with `server`: `"user-context7"` or `"user-apache-logs"`, and the tool name and arguments from the tool descriptors.
- Always read the tool schema/descriptor before calling if unsure about parameters.
