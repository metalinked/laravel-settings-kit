# Configuració recomanada per quan facis el repo PÚBLIC

## 1. Repository Settings → Branches (apareixerà després del primer push)

### Branch Protection Rules per `main`:
- ✅ Require a pull request before merging
- ✅ Require status checks to pass before merging
  - Tests
  - Code Style (si mantens aquest workflow)
- ✅ Require branches to be up to date before merging
- ✅ Require conversation resolution before merging
- ❌ Require signed commits (opcional)
- ✅ Include administrators (recomanat)
- ✅ Allow force pushes (només administradors)
- ✅ Allow deletions (només administradors)

## 2. Repository Settings → Actions → General

### Workflow permissions:
- ✅ Read and write permissions
- ✅ Allow GitHub Actions to create and approve pull requests

### Fork pull request workflows:
- ❌ Run workflows from fork pull requests (deixar desmarcat)
- ✅ Send write tokens to workflows from fork pull requests (només si necessites)
- ✅ Send secrets to workflows from fork pull requests (només si necessites)

## 3. Repository Settings → Code security and analysis

### Quan sigui públic, activa:
- ✅ Dependency graph
- ✅ Dependabot alerts
- ✅ Dependabot security updates
- ❌ Dependabot version updates (opcional, genera molts PRs)

## 4. Repository Settings → General

### Features:
- ✅ Issues
- ✅ Projects (opcional)
- ✅ Wiki (opcional)
- ✅ Discussions (opcional)

### Pull Requests:
- ✅ Allow merge commits
- ✅ Allow squash merging
- ✅ Allow rebase merging
- ✅ Always suggest updating pull request branches
- ✅ Allow auto-merge
- ✅ Automatically delete head branches
