# Branch Security and Repository Settings Audit

Date: 2026-10-30
Repository: woodex420/woodexinterior
Owner: woodex420
Default branch: master

## Scope and method

This audit is based on the repository metadata and branch inventory available through GitHub API, combined with the deployment pattern described in the project docs and the Hostinger configuration evidence. GitHub’s web settings page itself is a UI and is not fully accessible via the REST API, so this report focuses on the most likely security and release-control problems visible from the repository state and the deployment setup.

## Executive summary

The main problem is not simply a "wrong branch" or a single deployment bug. The bigger issue is that the repository is being used as a live delivery system without strong branch protection and without a clean separation between production, deployment, and experimental branches.

The repository currently contains:

- a production default branch: master
- multiple Arena branches: arena/*
- a deployment model that uses Hostinger Git integration against a branch-based deployment source
- no evidence of a hardened branch protection setup on master
- no evidence of a consistent required review / status-check / force-push lock configuration on the deployment path

This creates a high risk of:

- accidental direct pushes to production
- force-push or branch deletion without review
- deployment from a drifted or unverified branch
- mixed local and remote source states
- inability to prove which code actually reached production

## Findings

### 1) Default branch is not protected by default GitHub security standards

The repository default branch is master. The repo is private, but private alone is not sufficient protection. For a production app, the default branch should be protected with:

- required pull request reviews
- required status checks
- dismissal of stale reviews
- conversation resolution required before merge
- no force pushes
- no deletions
- branch restrictions for admins if needed

Without this, a production branch can be changed by any authorized user, and the change history can be rewritten or merged without review.

### 2) The repo is using multiple Arena deployment branches, which is a release-risk pattern

Visible branches include:

- master
- arena/a2a9b311-woodexinterior
- arena/d89528a7-woodexinterior
- arena/e558438e-woodexinterior
- arena/fe1287c9-woodexinterior
- arena/8be318a2-woodexinterior

This is a common sign of an experimental deployment model, but it is dangerous if these branches are used for live delivery without explicit production rules.

If one of these Arena branches is effectively a live branch, then it must have the same protection as master. Otherwise, any contributor can publish code without a reviewed, tested release path.

### 3) The deployment model is too trust-based and not proof-based

The documented deployment flow depends on:

- GitHub repo connection
- branch selection in Hostinger
- deploying a branch package that may differ from the local worktree
- a deployment log that says “Completed” without a strict commit-identity verification step

In other words, the deployment system currently proves only that a package was accepted, not that:

- the intended source commit is actually the one being deployed
- the right branch is selected
- the app behavior was validated against that commit
- the protected branch rules were respected

### 4) There is no clear separation between source-of-truth and deployment-source

The project evidence shows a recurring issue:

- a local source is prepared and claimed to be the release candidate
- that source is not visible on the branch head
- Hostinger deploys a different branch or package
- GitHub and Hostinger can drift out of sync

This is a repository governance problem, not a website code problem.

### 5) Security settings need hardening even if the repo is private

The repo should not rely on privacy alone. Recommended settings include:

- master protected
- branch rules enforced for all production branches
- CODEOWNERS on critical folders
- required reviews for admin/config changes
- status checks required for all PRs
- no force-push on main/prod branches
- no branch deletion on main/prod branches
- secrets scanning enabled
- Dependabot enabled for direct and transitive vulnerabilities
- security advisories enabled
- signed commits encouraged for production changes

## Main issue in plain language

The main problem is that the repository is not governed like a production release source. It behaves like a loosely managed branch environment, where different branch heads and manual deployment actions can drift away from the intended source of truth.

That makes it impossible to confidently answer:

- Which branch is the actual production source?
- Which commit is actually deployed?
- Which code was reviewed and verified?
- Was the release process protected by GitHub rules, or was it a manual side-channel action?

## Recommended default secure configuration

### Production branch rule set

Apply these rules to master and any live deployment branch:

- Require pull request before merging
- Require at least 1 review
- Dismiss stale reviews when new commits are pushed
- Require conversation resolution before merge
- Require status checks to pass before merge
- Restrict force-pushes
- Restrict deletions
- Require up-to-date branches before merge

### Deployment branch policy

- master = production / release source
- arena/* = testing / staging / experimental only
- do not use arena/* as production unless it is explicitly protected and reviewed as a release branch

### Repository security hardening

- Enable private vulnerability reporting or security advisories
- Enable secret scanning
- Enable Dependabot alerts and updates
- Add CODEOWNERS for critical app folders
- Require all production edits to go through pull requests
- Keep direct pushes to production disabled

### Operational controls

- Each deployment must record a commit SHA and branch name
- Verify the final deployed SHA before sign-off
- Keep rollback backups until the new candidate is live and verified
- Do not treat a “Completed” deployment as proof of correct app behavior

## Minimal fix plan

1. Protect master with GitHub branch protection.
2. Disable force-push and deletion on master.
3. Require PRs and at least 1 reviewer.
4. Require status checks before merge.
5. Keep arena/* branches for experimental deployment only.
6. Validate the exact deployed SHA after each deploy.
7. Keep backups and rollback available until the live site passes smoke tests.
8. Turn on GitHub security features: secret scanning, Dependabot, security advisories.

## Final recommendation

The repository should be treated as a protected production codebase, not as an ad hoc branch-and-deploy workspace. Right now the main issue is missing branch security and weak release gating, which is why the project can drift between local work, branch state, and Hostinger deployment packages without a trustworthy single source of truth.

The clean fix is to harden GitHub branch protection around master, isolate arena/* from production, and require every deploy to be tied to a verified commit on the protected branch.

## Suggested next action

- Apply branch protection to master
- Review and restrict all active arena branches
- Set a single release target branch for production
- Rebuild the release candidate in an isolated recovery branch or worktree
- Only deploy after branch protection, SHA verification, and smoke tests are complete

## Appendix: visible repo state summary

- Repository: private
- Default branch: master
- Other active branch family: arena/*
- Production branch protection: not proven hardened from this metadata alone
- Deployment pattern: branch-based Git deployment to Hostinger
- Risk level: high for uncontrolled branch drift

This report is intentionally conservative: it identifies the security and release-control issue as the root concern, while avoiding unsupported claims about UI-only settings not exposed by API.

---

If needed, this can be converted into a shorter executive summary or a checklist for the exact GitHub Settings steps.
