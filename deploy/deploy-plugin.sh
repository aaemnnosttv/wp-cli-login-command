#!/usr/bin/env bash
# Provide SSH access to the repository
eval "$(ssh-agent -s)"
chmod 600 deploy/key
ssh-add deploy/key

PLUGIN_REPO=git@github.com:aaemnnosttv/wp-cli-login-server.git

# Download git-subsplit
wget https://cdn.rawgit.com/dflydev/git-subsplit/d77ec9d3e1addd97dca1464eabf95c525f591490/git-subsplit.sh
# Prepare for doing the subsplits
bash git-subsplit.sh init git@github.com:aaemnnosttv/wp-cli-login-command.git
# synchronize the plugin directory with its respective repository for the current branch
bash git-subsplit.sh publish --heads=master --no-tags plugin/:${PLUGIN_REPO}

git clone ${PLUGIN_REPO} login-server
cd login-server

# The server plugin will be tagged 'server-x.x.x'.
# Strip the prefix and tag the server repo with 'x.x.x'
git tag ${TRAVIS_TAG#server-}
git push ${PLUGIN_REPO} --tags
