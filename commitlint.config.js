const ProjectPrefix = {
  ISSUE_PREFIXES: ["ss", "release"],
};

const issuePrefixes = `(${ProjectPrefix.ISSUE_PREFIXES.join("|")})`;

module.exports = {
  extends: ["@commitlint/config-conventional"],
  parserPreset: {
    parserOpts: {
      headerPattern: `^(\\w*)(\\(.+\\))?: .+ (${issuePrefixes})-\\d+$`,
      headerPatternCorrespondence: ["type", "scope", "issue"],
    },
  },
  rules: {
    "type-enum": [
      2,
      "always",
      [
        "build",
        "chore",
        "ci",
        "docs",
        "feat",
        "fix",
        "perf",
        "refactor",
        "revert",
        "style",
        "test",
      ],
    ],
  },
};
