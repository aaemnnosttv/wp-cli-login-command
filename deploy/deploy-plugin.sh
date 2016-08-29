# Provide SSH access to the repository
eval "$(ssh-agent -s)"
chmod 600 deploy/key
ssh-add deploy/key

# Install git-subsplit
(
    cd /tmp
    git clone https://github.com/dflydev/git-subsplit.git
    bash git-subsplit/install.sh
)
# Prepare for doing the subsplits
git subsplit init git@github.com:aaemnnosttv/wp-cli-login-command.git
# synchronize the plugin directory with its respective repository for the current branch
git subsplit publish --heads="$TRAVIS_BRANCH" plugin/:git@github.com:aaemnnosttv/wp-cli-login-server.git
