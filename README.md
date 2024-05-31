# PR Risky Zones Highlighter

## Description

The PR Risky Zones Highlighter is a GitHub Action designed to enhance the code review process by identifying potentially risky areas in pull requests. It utilizes the capabilities of ChatGPT to analyze changes and pinpoint sections of code that may contain vulnerabilities or are prone to errors. This action aims to assist reviewers by focusing their attention on critical parts of the code that need thorough scrutiny.

Remember that ChatGPT API isn't for free. The action uses gpt-3.5-turbo.

## Inputs

### Required Inputs

- `gpt_api_key`: The API key for accessing ChatGPT to analyze the pull request.
  - **Required**: Yes
  - **Example**: sk-g33uNV6xasvglAk14N5chOQsFcs1lsFi

- `gpt_url`: The URL to ChatGPT.
  - **Required**: Yes
  - **Example**: https://api.proxyapi.ru/openai (use this proxy, if you are in Russia) or https://api.openai.com.

- `github_token`: The GitHub token used to fetch pull request details and post comments.
  - **Required**: Yes
  - You don't need to add it to secrets manually, GitHub will do it

- `repo_full_name`: The full name of the repository that the pull request is made to, e.g., "octocat/hello-world".
  - **Required**: Yes
  - You don't need to add it to secrets manually, GitHub will do it

- `pull_number`: The number associated with the pull request to analyze.
  - **Required**: Yes
  - You don't need to add it to secrets manually, GitHub will do it

- `max_comments`: The limit for ChatGPT to give comments. For example, if set to 1, only one, the riskiest part of the 
PR will be highlighted
  - **Required**: No
  - If not provided, the CHatGPT wouldn't be limited

## Outputs

This action does not generate any outputs except for posting comments directly on the pull request based on the analysis results.

## Secrets

The action uses the following secrets to ensure safe and authorized interactions with GitHub and ChatGPT:

- `GPT_API_KEY`: Used to authenticate and interact with ChatGPT for analyzing pull requests.
- `GITHUB_TOKEN`: Used to interact with GitHub API for fetching pull request data and posting comments.

## Environment Variables

This action does not use additional environment variables beyond the inputs required.

## Usage

To use the PR Risky Zones Highlighter in your workflow, add the following step to your GitHub Actions workflow file (e.g., `.github/workflows/main.yml`):

```yaml
name: Highlight Risky Zones in PRs

on:
  pull_request:
    types: [opened, synchronize]

jobs:
  analyze_pr:
    runs-on: ubuntu-latest

    steps:

    - name: Highlight Risky Zones in PRs
      uses: savinmikhail/pr_risky_zones_highlighter@0.1.x
      with:
        gpt_api_key: ${{ secrets.GPT_API_KEY }}
        github_token: ${{ secrets.GITHUB_TOKEN }}
        repo_full_name: ${{ github.repository }}
        pull_number: ${{ github.event.pull_request.number }}
```
For testing see the https://github.com/savinmikhail/test_risk_zone_highlighter_action

There ypu can create some PRs and see the result.


